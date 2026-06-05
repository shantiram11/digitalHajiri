# Architecture — e-Hajiri

> System architecture, design decisions, and trade-offs for the Biometric Attendance Management System. Every decision below has a stated *why* so it can be revisited as the system evolves.

---

## 1. High-Level Architecture

```
┌────────────────────────────────────────────────────────────────────┐
│                          Vue 3 + Vite SPA                          │
│      (Pinia stores, Vue Router, Axios, Tailwind, vue-i18n)         │
└──────────────────────────────┬─────────────────────────────────────┘
                               │  HTTPS / Sanctum tokens
                               ▼
┌────────────────────────────────────────────────────────────────────┐
│                  Laravel API (PHP 8.3, Sanctum)                    │
│  Controllers → FormRequests → Services → Repositories → Models     │
│         Policies for authorization, DTOs at service boundaries     │
└──────────────────────────────┬─────────────────────────────────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        ▼                      ▼                      ▼
 ┌──────────────┐      ┌───────────────┐      ┌──────────────┐
 │  Attendance  │      │     Leave     │      │   Reports    │
 │   Services   │      │    Services   │      │   Services   │
 └──────┬───────┘      └───────────────┘      └──────────────┘
        │
        ▼
┌────────────────────────────────────────────────────────────────────┐
│              Device Integration Layer (interface-driven)           │
│   AttendanceDeviceInterface ← ZKTecoDevice, HikvisionDevice, ...   │
└──────────────────────────────┬─────────────────────────────────────┘
                               │ TCP / SDK / HTTP / Webhook
                               ▼
                ┌───────────────────────────────┐
                │   Biometric Devices (ZKTeco,  │
                │   Hikvision, future vendors)  │
                └───────────────────────────────┘

         ┌────────────┐    ┌────────────┐    ┌────────────┐
         │  MySQL 8   │    │   Redis    │    │  Horizon   │
         │ (primary)  │    │ (cache/q)  │    │ (queues)   │
         └────────────┘    └────────────┘    └────────────┘
```

### Why this shape

- **Frontend and API are decoupled** so a future mobile app uses the exact same API. No server-rendered pages, no Blade for app screens.
- **Service Layer between Controllers and Models** so business logic is unit-testable without HTTP and reusable from queue jobs, scheduler, and Artisan commands.
- **Device Integration Layer is interface-first** so ZKTeco is one of many implementations, not the architecture itself.
- **Queue workers and Horizon** because sync, summary generation, and reporting must never block HTTP requests.

---

## 2. Module Breakdown

| Module | Namespace | Responsibility |
|---|---|---|
| `Organization` | `App\Domain\Organization` | Tenant root, settings, fiscal year, timezone |
| `Identity` | `App\Domain\Identity` | Users, roles, permissions, authentication |
| `Employee` | `App\Domain\Employee` | Employees, departments, designations, device mappings |
| `Device` | `App\Domain\Device` | Devices, vendor abstraction, sync state |
| `Attendance` | `App\Domain\Attendance` | Events, summaries, rule engine, calculator |
| `Leave` | `App\Domain\Leave` | Leave types, balances, requests, approvals |
| `Holiday` | `App\Domain\Holiday` | Holiday calendar, weekly off |
| `Correction` | `App\Domain\Correction` | Attendance corrections + approvals |
| `Report` | `App\Domain\Report` | Report generation and caching |
| `Audit` | `App\Domain\Audit` | Append-only audit logs |
| `Calendar` | `App\Domain\Calendar` | AD↔BS, fiscal year, timezone |
| `Notification` | `App\Domain\Notification` | In-app + email dispatch |

### Why Domain-Driven module folders instead of `app/Models`, `app/Services` flat layout

- Each module is independently navigable; new developers find every Attendance concern in one folder.
- Future extraction into separate packages (or microservices) is mechanically simple.
- Cross-module dependencies are explicit (a service imports from another module's namespace), preventing the "everything depends on everything" rot.

---

## 3. Domain Models

### Core relationships

```
Organization *──* User       (via organization_user; Phase 4 — many-to-many)
                              (users.organization_id remains as a *default* org pointer
                               for SPA login convenience; not the authority)
Organization 1──* Department 1──* Employee
Organization 1──* Designation
Organization 1──* Device
Employee     *──* Device   (via employee_device_mappings)
Employee     1──* AttendanceEvent
Employee     1──* AttendanceSummary   (one per day per shift)
Employee     1──* LeaveRequest
Employee     1──* LeaveBalance        (one per fiscal year per leave type)
Organization 1──* Holiday
Organization 1──* AttendanceRule
Organization 1──* ApprovalWorkflow
* (any auditable model) 1──* AuditLog
```

> **User vs Employee separation (Phase 4 confirmation).** A `User` is an authentication identity — email, password, sessions, tokens. An `Employee` is an HR record — payroll, department, attendance, leave. They are linked by `employees.user_id` (nullable) so most employees do not need login accounts (think: field workers who only punch a fingerprint), while every login account is a `User` regardless of whether they correspond to any `Employee`. This separation lets us support consultants, platform support, and auditors who have no employee record at all, and it keeps attendance data attached to the right legal entity even after a user account is deleted.

### Key entities (logical, not yet code)

#### Employee
- Belongs to one Organization, one Department, one Designation.
- Has many AttendanceEvents, AttendanceSummaries, LeaveRequests.
- Has many DeviceMappings (one per device the employee uses).
- Soft-deletable (status `resigned` / `retired`) but data is preserved.

#### AttendanceEvent
- **Append-only**. Immutable after insert.
- Belongs to Organization + Employee + Device.
- Carries `device_user_id` so events from unmapped users are still captured (orphaned events).
- Stores raw vendor payload as JSON for future re-processing.

#### AttendanceSummary
- Derived. Regenerated from events any time events change or rules change.
- One per `(employee_id, work_date)` for single-shift; one per `(employee_id, shift_id, work_date)` for multi-shift.
- Stores computed fields: check-in, check-out, worked minutes, late minutes, overtime minutes, status, source of truth markers (which events, which leave, which holiday).

#### AttendanceRule
- Per-organization (default), overridable per-department or per-employee.
- Fields: office_start_time, office_end_time, grace_minutes, half_day_threshold_minutes, absent_threshold_minutes, ot_threshold_minutes, weekly_off_days (JSON), pairing_strategy.

---

## 4. Database Design

### Conventions
- All tables: `id` BIGINT unsigned PK, `created_at`, `updated_at`.
- All tenant-scoped tables: `organization_id` BIGINT NOT NULL with index.
- Soft deletes only where business-meaningful — **never** on `attendance_events`, `audit_logs`, `attendance_corrections`.
- Timestamps stored UTC; timezone resolution at read time.
- Money/decimals use `DECIMAL(p,s)`, never `FLOAT`.
- Enums implemented as PHP enum classes mapped to `VARCHAR` (not MySQL ENUM) so schema migrations don't lock the table.

### Tables (MVP)

| Table | Purpose | Key columns |
|---|---|---|
| `organizations` | Tenant root | name, slug, timezone, fiscal_year_start, settings (json), status |
| `users` | Auth identities | organization_id (nullable — default org for SPA), name, email, password, status |
| `organization_user` | **Phase 4** membership join | organization_id, user_id, status, joined_at, invited_by_user_id |
| `organization_invitations` | **Phase 4** | organization_id, email, role_id, token, expires_at, accepted_at |
| `permissions` (Spatie) | Granular permissions | name, guard_name (global catalog) |
| `roles` (Spatie + custom) | RBAC roles | name, guard_name, organization_id (nullable = system role) |
| `role_has_permissions` (Spatie) | M-M | role_id, permission_id |
| `model_has_roles` (Spatie + teams) | Role assignment | role_id, model_type, model_id, team_id (= organization_id) |
| `model_has_permissions` (Spatie + teams) | Direct user perms | permission_id, model_type, model_id, team_id (= organization_id) |
| `departments` | Org units | organization_id, parent_id, name, head_employee_id |
| `designations` | Job titles | organization_id, name, pay_grade |
| `employees` | Staff | organization_id, department_id, designation_id, user_id (nullable), employee_code, name, citizenship_no, joined_at, status |
| `devices` | Biometric devices | organization_id, vendor, model, serial, name, location, ip, port, settings (json), last_online_at, last_sync_at, last_log_id, device_time, server_time, status |
| `employee_device_mappings` | Employee↔Device user ID | employee_id, device_id, device_user_id |
| `attendance_events` | Raw punches (append-only) | organization_id, employee_id (nullable for orphans), device_id, device_user_id, event_timestamp (UTC), verification_method, raw_payload (json), source, ingested_at |
| `attendance_rules` | Configurable rules | organization_id, department_id (nullable), employee_id (nullable), office_start, office_end, grace_minutes, ..., effective_from, effective_to |
| `attendance_summaries` | Computed per day | organization_id, employee_id, work_date, status, check_in_at, check_out_at, worked_minutes, late_minutes, early_minutes, overtime_minutes, is_late, is_early, sources (json), rule_id, generated_at |
| `leave_types` | Configurable types | organization_id, name, slug, default_quota, requires_attachment_after_days, is_paid, settings (json) |
| `leave_balances` | Per employee per year | organization_id, employee_id, leave_type_id, fiscal_year, opening, accrued, consumed, adjusted, closing |
| `leave_requests` | Applications | organization_id, employee_id, leave_type_id, from_date, to_date, days, session, reason, status, applied_at |
| `approval_workflows` | Configurable | organization_id, name, target_type (leave/correction), steps (json) |
| `approvals` | Polymorphic approval steps | workflow_id, approvable_type, approvable_id, step, approver_user_id, status, acted_at, comment |
| `holidays` | Calendar | organization_id (nullable for national), date (AD), name, type, recurrence |
| `attendance_corrections` | Requests | organization_id, employee_id, work_date, requested_check_in, requested_check_out, reason, status, requested_by_user_id |
| `attendance_correction_approvals` | Steps | correction_id, step, approver_user_id, status, acted_at, comment |
| `audit_logs` | Append-only | organization_id (nullable), user_id, action, auditable_type, auditable_id, before (json), after (json), ip, user_agent, created_at |
| `personal_access_tokens` | Sanctum | (standard) |
| `jobs`, `failed_jobs` | Laravel queue | (standard) |

### Indexing strategy

- `attendance_events(organization_id, employee_id, event_timestamp)` — primary query path for summary generation.
- `attendance_events(device_id, event_timestamp)` — sync gap detection.
- `attendance_events(event_timestamp)` — for partitioning by year (later).
- `attendance_summaries(organization_id, employee_id, work_date)` UNIQUE — one summary per day per employee.
- `attendance_summaries(organization_id, work_date)` — daily report by org.
- `audit_logs(auditable_type, auditable_id)` — fetch history of one entity.
- `users(email)` UNIQUE.

### Partitioning (deferred, Phase 2)
- `attendance_events` partitioned by `YEAR(event_timestamp)` once a tenant crosses ~10M events. Until then, indexes suffice.

---

## 5. API Design Strategy

### Conventions
- Versioned: `/api/v1/...`
- Resource-oriented: `/api/v1/employees`, `/api/v1/attendance/events`, `/api/v1/attendance/summaries`.
- Authentication via Sanctum bearer tokens (SPA cookie auth also supported via Sanctum stateful domains).
- Every response wrapped in `{ data, meta?, links? }` using Laravel API Resources.
- Errors via RFC 7807-style problem details: `{ type, title, status, detail, errors? }`.
- Pagination default 25, max 100, cursor-based for events.
- Filtering via query string: `?filter[department_id]=5&filter[date_from]=2026-06-01`.
- Sorting: `?sort=-event_timestamp`.
- Sparse fieldsets: `?fields[employees]=id,name,department_id`.

### Tenant scoping
- All routes (except super-admin and public auth) pass through `EnsureOrganizationContext` middleware that sets `app('current_organization')` from the authenticated user's organization.
- Every Eloquent query auto-scopes via a global scope on tenant-bound models.

### Why not GraphQL
- Reports + sync + audit queries are well-known and stable; REST + sparse fieldsets covers our needs with less operational overhead and easier caching.

---

## 6. Queue Strategy

### Connections
- **default** — sync work, summary generation, exports.
- **notifications** — emails, in-app.
- **maintenance** — long-running cleanup, partitioning.

### Critical jobs
| Job | Trigger | Connection |
|---|---|---|
| `SyncDeviceLogsJob` | Scheduler every 5 min, or manual | default |
| `IngestAttendanceEventsJob` | Chained after `SyncDeviceLogsJob` | default |
| `GenerateAttendanceSummariesJob` | Chained after ingest; also on rule change | default |
| `RecomputeEmployeeSummariesJob` | On employee changes, leave approval, correction approval | default |
| `MonitorDeviceHealthJob` | Scheduler every minute | maintenance |
| `SendReportEmailJob` | Scheduler / user request | notifications |
| `ExportReportJob` | User request | default |

### Idempotency
- `SyncDeviceLogsJob` uses `devices.last_log_id` so retries don't double-ingest.
- `IngestAttendanceEventsJob` deduplicates on `(device_id, device_user_id, event_timestamp)` — same punch from a retried batch becomes a no-op.
- `GenerateAttendanceSummariesJob` is per `(employee_id, work_date)`; running it twice yields the same summary.

### Why Horizon
- We need queue visibility, retries, throttling, and per-tenant metrics. Building this ourselves is wasted effort.

---

## 7. Synchronization Strategy

### Pull model (MVP)
Most ZKTeco devices expose a TCP-based protocol; we poll them.

1. Scheduler triggers `SyncDeviceLogsJob` per device every 5 minutes (configurable per device).
2. Job opens the device connection through the `AttendanceDeviceInterface`.
3. Job fetches logs since `devices.last_log_id` (or full sync if first time).
4. Logs are passed to `IngestAttendanceEventsJob` which writes raw events.
5. `last_log_id` and `last_sync_at` updated only after successful ingest.
6. Affected employee summaries are recomputed via `GenerateAttendanceSummariesJob`.

### Push model (Phase 2)
- Devices that support push (ZKTeco PUSH SDK, some Hikvision) call our `/api/v1/devices/{device}/events` webhook.
- Same `IngestAttendanceEventsJob` is invoked, decoupling ingest from transport.

### Full vs incremental sync
- `Full sync` = ignore `last_log_id`, fetch all device logs, dedupe on insert.
- `Incremental sync` = default; uses `last_log_id`.

### Why a job chain
- Each step is independently retryable. If summary generation fails, raw events are still safely persisted.

### Offline tolerance
- Devices buffer punches internally (most vendors support 100k+ records).
- When network returns, the next sync pulls everything since `last_log_id` — no special recovery code needed.
- Operator UI surfaces "last sync N minutes ago" and "device offline since X" so gaps are visible.

---

## 8. Security Strategy

### Authentication
- Sanctum bearer tokens for API clients (mobile, integrations).
- Sanctum stateful (cookie) auth for the SPA when served from the same root domain.
- Tokens carry abilities (scopes) — e.g., `attendance.read`, `device.sync`.

### Authorization
- Policies on every model that has tenant data — they answer record-level "can this user touch *this*."
- Permissions (capabilities) are gated by `PermissionResolver` + Gate. They answer "can this user do this kind of thing at all."
- **A user may belong to many organizations** with different roles in each (Phase 4). The active org is resolved per request from the bearer token's `org:` ability → `X-Organization-Id` header → session attribute → `users.organization_id` default.
- See [docs/authorization.md](authorization.md) for the full resolution pipeline, caching strategy, and permission catalog.

### Tenant isolation
- Global Eloquent scope on every tenant-bound model.
- Foreign keys always include `organization_id` redundantly so cross-tenant joins are physically impossible by query plan.
- Repository methods accept `Organization` (or resolve via current context) and never trust user-supplied tenant IDs.

### Biometric data
- **No fingerprint images stored**. **No fingerprint templates stored**.
- Templates remain on the device. The application sees only the resulting punch event.
- `verification_method` records *how* the user verified (fingerprint / face / card / manual / mobile), not the biometric itself.

### Transport
- HTTPS required for all traffic. HSTS enabled.
- Device-to-server: TLS where vendor supports it; private VLAN otherwise.

### Secrets
- `.env` for local; vault (e.g., Doppler, AWS Secrets Manager) for production. Document this; do not commit secrets.

### Audit
- Eloquent observer on every `Auditable` model writes to `audit_logs` on `created`, `updated`, `deleted`.
- Login, logout, failed-login, impersonation also logged.

---

## 9. Scalability Considerations

| Concern | Strategy |
|---|---|
| 1,000 orgs × 5,000 employees × 5 devices | MySQL handles fine at this scale with the index plan above. |
| 10M+ events per year per large org | Partition `attendance_events` by year at Phase 2. Until then, composite indexes suffice. |
| Summary generation hot path | Recompute only affected `(employee_id, work_date)` pairs, not full sweeps. |
| Report generation | Always from `attendance_summaries`, never from raw events. Cache PDF/Excel outputs for 24h. |
| Read scaling | MySQL read replica for reports; primary for events. Deferred until proven needed. |
| Cache | Redis for sessions, rate limits, summary cache, idempotency keys. |
| Queue scaling | Horizon auto-scales workers per connection load. |
| Device fleet | Per-device sync jobs are independent; one slow device cannot stall others. |

---

## 10. Configuration & Multi-Tenancy

### Tenancy model
- **Single database, shared schema, tenant column**.
- Every tenant-bound row carries `organization_id`.
- A global scope and middleware enforce isolation.

### Why not database-per-tenant
- Easier ops at MVP, simpler migrations, and the data sensitivity is moderate (no payment data at MVP). Database-per-tenant is reserved for enterprise customers later.

### Per-organization settings
- Stored as JSON in `organizations.settings` plus typed columns for hot paths (timezone, fiscal_year_start).
- Surfaced through `OrganizationSettings` value object with typed getters.

---

## 11. Calendar & Timezone Strategy

- Storage: UTC `DATETIME` for timestamps, AD `DATE` for date fields.
- Org timezone resolved in `OrganizationContext` and made available to formatters.
- `CalendarService` provides AD↔BS conversion via a maintained lookup table (BS years cover at least 2070–2100 BS at MVP).
- Fiscal year resolution: `FiscalYearService::current(Organization $org): FiscalYear` → `{ start_date_ad, end_date_ad, label_bs }`.

---

## 12. Frontend Architecture

### Layout
```
resources/js/
├── app.ts                    # Vue + Pinia + Router bootstrap
├── router/                   # routes per module
├── stores/                   # Pinia stores per module
├── modules/
│   ├── auth/
│   ├── organization/
│   ├── employee/
│   ├── device/
│   ├── attendance/
│   ├── leave/
│   ├── report/
│   └── audit/
├── components/               # shared, dumb components
├── composables/              # useApi, useAuth, useToast, useCalendar
├── services/                 # axios clients per module
├── locales/                  # en.json, ne.json (later)
└── types/                    # TS types matching API resources
```

### Why module folders mirror backend modules
- A developer working on Leave touches `app/Domain/Leave` and `resources/js/modules/leave/` — predictable.
- Lazy-loaded route chunks per module keep initial bundle small.

### State strategy
- Pinia stores hold normalized server state per module.
- Components consume composables, not stores directly, so refactors stay local.
- Forms use VeeValidate + Zod schemas (chosen because schemas can be reused for type generation).

### API client
- Axios instance with interceptors for: auth token, organization context, error normalization, 401 redirect.

### Calendar UI
- `<BsAdDate :date="...">` component switches display between BS and AD based on user preference.
- Datepickers accept both AD and BS input and emit AD ISO strings.

---

## 13. Testing Strategy

- **Unit**: services, calculators, rule engine, calendar conversions. PHPUnit, fast.
- **Feature**: controllers + policies + middleware via Laravel HTTP tests.
- **Integration**: device drivers tested against a `FakeAttendanceDevice` implementation.
- **Frontend**: Vitest for composables and stores; Playwright for smoke flows (login → view attendance).
- CI runs all of the above on every PR.

---

## 14. Deployment (sketch)

- App: Laravel on PHP-FPM behind Nginx or Laravel Octane (later).
- DB: MySQL 8 with daily backups, point-in-time recovery enabled.
- Queue: Horizon with Redis.
- Scheduler: `php artisan schedule:run` via cron or supervisor.
- Static frontend: built by Vite, served by Nginx alongside the API.
- Observability: Telescope (dev), Sentry (prod), structured JSON logs to a central store.

---

## 15. Open Architectural Decisions (revisit later)

| Decision | When to revisit |
|---|---|
| Single-DB vs DB-per-tenant | When first enterprise tenant arrives. |
| Pull vs push sync model | When a vendor with push support becomes primary. |
| Read replica for reports | When p95 report latency degrades. |
| Event sourcing for attendance | Probably never — raw events already give us the equivalent benefits. |
| GraphQL | If a third-party client needs flexible field selection. |

---

## 16. Why These Decisions

The over-arching theme: **the application database is the source of truth, and the architecture preserves that invariant under every failure mode** — device offline, device replaced, vendor change, rule change, retroactive correction, employee re-mapping. Every choice above (append-only events, derived summaries, interface-driven devices, idempotent jobs, policy-driven authorization, tenant column with global scope) exists to keep that invariant true without making the developer experience painful.
