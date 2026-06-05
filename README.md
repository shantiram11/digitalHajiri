# e-Hajiri (Digital Hajiri)

Biometric Attendance Management System for **Nepali municipalities, ward offices, rural municipalities (Gaunpalika), schools, and government offices**. Initial device support is **ZKTeco**, but the architecture treats vendor as a plug-in — adding Hikvision or any other vendor is a one-class change.

> **Phase 1–3 status**: documentation + scaffold + schema complete. Business-logic phases (5–8) are stubbed with clear `TODO(phase-N)` markers so contributors know where to start.

---

## Read the docs first

Documentation comes before code. Every architectural decision is explained in:

| Document | What it covers |
|---|---|
| [docs/requirements.md](docs/requirements.md) | Business goals, user types, MVP modules, government & Nepal-specific requirements, non-functional targets, out-of-scope. |
| [docs/features-to-consider.md](docs/features-to-consider.md) | Every attendance, leave, government, and Nepal-specific feature with scope (MVP / Phase 2 / Future) and its architectural implication. |
| [docs/architecture.md](docs/architecture.md) | High-level diagram, the 12 domain modules, full DB design with indexes, API strategy, queue strategy, sync strategy, security, scalability, frontend layout, testing — with **why** for every decision. |
| [docs/device-integration.md](docs/device-integration.md) | The 7 invariants the device layer must hold, the full `AttendanceDeviceInterface` definition with DTOs, ZKTeco quirks, the idempotent sync workflow, and the **vendor contract test suite** so a new vendor is a mechanical exercise. |

If you change the architecture, update the doc in the same PR. Stale architecture docs are worse than no docs.

---

## Stack

### Backend
- **PHP 8.3+** (project tested on 8.4)
- **Laravel 13** (latest stable at time of scaffold)
- **MySQL 8** (SQLite for local development)
- **Laravel Sanctum** — bearer & SPA cookie auth
- **Laravel Horizon** — queue dashboard & worker autoscaling
- **Laravel Queues** — Redis-backed in production
- **Laravel Scheduler** — per-device sync, health monitoring

### Frontend
- **Vue 3** + **TypeScript** + **Vite 8**
- **Pinia** (state) + **Vue Router** (lazy-loaded module chunks)
- **Tailwind CSS 4** + **Axios** + **vue-i18n** (English at MVP, Nepali scaffolded)

### Principles enforced in the scaffold
- SOLID + interface-driven design (`AttendanceDeviceInterface`, `PairingStrategy`, `DeviceConnectorFactoryInterface`)
- Repository pattern where it pays off (reports, audit queries)
- Service layer between controllers and models — every domain module has its own `Services/` folder
- DTOs at all transport boundaries (`AttendanceLogDto`, `DeviceInfoDto`, `SyncResultDto`)
- Multi-tenant from day one (global scope + middleware + `organization_id` on every row)
- API-first — no Blade for app screens; Blade only serves the SPA shell

---

## Project layout

```
.
├── app/
│   ├── Domain/                           # 12 bounded contexts, one folder each
│   │   ├── Attendance/                   # Calculator, RuleResolver, strategies, jobs, models
│   │   ├── Audit/                        # Append-only audit log
│   │   ├── Calendar/                     # AD ↔ BS, fiscal year
│   │   ├── Correction/                   # Attendance correction workflow
│   │   ├── Device/                       # ◀ The vendor-agnostic device layer
│   │   │   ├── Contracts/                #   AttendanceDeviceInterface lives here
│   │   │   ├── DataTransferObjects/      #   All four DTOs
│   │   │   ├── Drivers/Fake/             #   Reference implementation
│   │   │   ├── Drivers/ZKTeco/           #   Phase-5 skeleton
│   │   │   ├── Factories/                #   DeviceConnectorFactory
│   │   │   ├── Jobs/                     #   SyncDeviceLogsJob, MonitorDeviceHealthJob
│   │   │   ├── Services/                 #   DeviceSyncService
│   │   │   └── DeviceServiceProvider.php
│   │   ├── Employee/                     # Employee, Department, Designation
│   │   ├── Holiday/
│   │   ├── Identity/                     # Roles, permissions, approvals
│   │   ├── Leave/                        # Types, balances, requests
│   │   ├── Notification/
│   │   ├── Organization/                 # Tenant root + OrganizationContext
│   │   └── Report/
│   ├── Http/
│   │   ├── Controllers/Api/V1/           # 16 module controllers + Auth
│   │   └── Middleware/EnsureOrganizationContext.php
│   ├── Models/User.php                   # The one Eloquent model that lives outside Domain
│   └── Support/
│       ├── Concerns/                     # BelongsToOrganization, Auditable
│       └── Eloquent/
│           ├── Observers/AuditObserver.php
│           └── Scopes/OrganizationScope.php
├── bootstrap/
│   ├── app.php                           # Sanctum + organization middleware wiring
│   └── providers.php                     # AppServiceProvider + DeviceServiceProvider
├── database/
│   └── migrations/                       # 21 migrations covering the full MVP schema
├── docs/                                 # ◀ Start here
│   ├── architecture.md
│   ├── device-integration.md
│   ├── features-to-consider.md
│   └── requirements.md
├── resources/
│   ├── js/
│   │   ├── app.ts                        # Pinia + Router + i18n bootstrap
│   │   ├── App.vue
│   │   ├── components/BsAdDate.vue       # The AD/BS-aware date component
│   │   ├── composables/useCalendar.ts
│   │   ├── locales/en.json
│   │   ├── modules/                      # Per-domain folders (views/stores/services/types)
│   │   │   ├── attendance/  audit/  auth/
│   │   │   ├── correction/  device/  employee/
│   │   │   ├── holiday/     leave/   organization/
│   │   │   └── report/
│   │   ├── router/index.ts               # Lazy-loaded route chunks
│   │   ├── services/http.ts              # Axios + Sanctum + tenant header + 401 redirect
│   │   └── views/DashboardView.vue
│   └── views/app.blade.php               # SPA shell
├── routes/
│   ├── api.php                           # 35 v1 endpoints under /api/v1
│   ├── console.php                       # Scheduler: sync + health
│   └── web.php                           # SPA catch-all
└── vite.config.ts
```

---

## Architectural commitments — short version

These are non-negotiable, encoded throughout the code, and explained in `docs/`:

1. **DB is the source of truth, not the device.** Devices generate events.
2. **`attendance_events` is append-only.** No edits, no deletes. Corrections append corrective events.
3. **Vendor-agnostic device layer.** Every consumer talks to `App\Domain\Device\Contracts\AttendanceDeviceInterface`. ZKTeco is one of N drivers.
4. **Employee ≠ device user.** Mappings live in `employee_device_mappings`.
5. **Multi-tenant from day one.** `organization_id` on every tenant-bound row + global scope + middleware.
6. **Idempotent sync.** Unique natural-key index on `attendance_events` + cursor advancement only after successful ingest.
7. **No biometric templates ever stored on the server.** `verification_method` is recorded; the biometric itself is not.
8. **Configurable rules, not hardcoded ones.** Office times, grace, OT, weekly-off, half-day thresholds — all in `attendance_rules`, overridable per department or per employee.
9. **AD stored, AD or BS displayed.** All dates stored as AD; the UI switches to BS via `useCalendar` and `<BsAdDate>`.
10. **Audit everything.** The `Auditable` trait + `AuditObserver` capture every create/update/delete with actor, IP, before/after.

---

## Quick start

Prerequisites: PHP 8.3+, Composer, Node 20+, MySQL 8 (or use SQLite for local dev), Redis (production queues).

```bash
# Install
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh

# Run
composer run dev       # serves API + queue + logs + Vite concurrently
```

URLs:
- **App**: <http://localhost:8000>
- **Horizon**: <http://localhost:8000/horizon>
- **Health probe**: <http://localhost:8000/up>

---

## Development phases

| Phase | Status | Scope |
|---|---|---|
| **1. Documentation** | ✅ done | All four `docs/*.md` files. |
| **2. Project Scaffold** | ✅ done | Laravel, Vue, Tailwind, folder structure, providers, routes, controllers, interfaces, DTOs. |
| **3. Database Design** | ✅ done | 21 migrations, models, traits, global scope. |
| **4. Auth & Permissions** | ▢ next | Roles/permissions seeders, policies, super-admin login flow, profile management. |
| **5. Device Integration** | ▢ | Fill `ZKTecoDevice` driver. Vendor contract test suite. Provisioning UI. |
| **6. Attendance Engine** | ▢ | Finish `AttendanceCalculator`, add `PairInOutStrategy`, night/split shifts, comprehensive tests. |
| **7. Leave Management** | ▢ | Approval workflow engine, balance accrual scheduler, mailable notifications. |
| **8. Reports** | ▢ | PDF/Excel exporters, scheduled email reports, custom-range builder. |
| **9. Testing & Hardening** | ▢ | End-to-end Playwright flows, contract tests, load tests for summary generation. |

Each phase has clear `TODO(phase-N)` markers in the code where work begins.

---

## Adding a new device vendor

Two files. That's the whole change.

1. Create `app/Domain/Device/Drivers/{Vendor}/{Vendor}Device.php` implementing `AttendanceDeviceInterface`.
2. Register in `app/Domain/Device/Factories/DeviceConnectorFactory::$drivers`.

Then run the vendor contract test suite against it — the same suite `FakeDevice` passes. See `docs/device-integration.md` §8 for the contract guarantees the test suite verifies.

---

## Why these decisions

The over-arching theme: **the application database is the source of truth, and the architecture preserves that invariant under every failure mode** — device offline, device replaced, vendor change, rule change, retroactive correction, employee re-mapping. Every choice in the scaffold (append-only events, derived summaries, interface-driven devices, idempotent jobs, policy-driven authorization, tenant column with global scope) exists to keep that invariant true without making the developer experience painful.

Read `docs/architecture.md` for the full reasoning. Every section ends with a *why* paragraph.
