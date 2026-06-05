# Requirements — e-Hajiri (Digital Hajiri)

> Biometric Attendance Management System for Nepal Municipalities, Ward Offices, Rural Municipalities (Gaunpalika), Schools, and Government Offices.

---

## 1. Business Goals

1. **Replace paper-based attendance** in Nepali local government offices, schools, and rural municipalities with a verifiable digital trail.
2. **Provide an auditable source of truth** for staff attendance that can be presented during government audits (e.g., OAG audits), legal disputes, and salary disbursement.
3. **Standardize attendance policy** across departments while allowing per-organization configuration (office timings, grace minutes, half-day rules, overtime).
4. **Support multi-location government bodies** — a municipality may have several ward offices, each with their own biometric device, all rolling up to one central attendance record.
5. **Eliminate vendor lock-in** to a single biometric device manufacturer (initially ZKTeco, later Hikvision and others).
6. **Operate reliably in low-connectivity environments** common in rural Nepal — devices must continue collecting punches when offline and sync when the network returns.
7. **Enable government reporting workflows** — daily, monthly, and annual attendance reports in formats acceptable to Nepali government bodies.
8. **Provide long-term retention** of raw attendance data for legal and audit purposes (minimum 7 years recommended; configurable).
9. **Lay a foundation for payroll, performance tracking, and HR modules** without rearchitecting later.

---

## 2. User Types

### 2.1 Super Admin
- Owns the SaaS / platform itself.
- Creates and suspends organizations.
- Manages global settings, system updates, and platform-wide audit logs.
- Can impersonate organization admins for support purposes (logged).
- Manages global leave types, holiday templates, and default attendance policies.

### 2.2 Organization Admin
- A municipality CAO, school principal, or HR head.
- Manages a single organization's:
  - Employees, departments, designations
  - Devices and device-employee mappings
  - Attendance rules (office timings, grace, shifts)
  - Leave types and balances
  - Holidays
  - Approval workflows
  - Reports
- Can approve attendance corrections.
- Cannot access other organizations.

### 2.3 Attendance Officer
- Day-to-day operator (e.g., section officer, HR assistant).
- Reviews daily attendance.
- Initiates and processes attendance corrections.
- Manages device synchronization.
- Runs and exports reports.
- Cannot change organization-wide rules.

### 2.4 Employee
- Views own attendance history.
- Applies for leave.
- Requests corrections on own attendance (e.g., "forgot to punch out").
- Views personal reports and leave balance.
- Cannot view or modify others' data.

> Permissions are RBAC-driven via Spatie's permission tables with tenant-aware customization (see [authorization.md](authorization.md)). The four user types above map onto default roles. A single user may belong to **many organizations** with different roles in each — consultants, regional administrators, and platform support staff all use this — so role assignments are per-membership, not per-user.

---

## 3. Core Modules (Phase 1 — MVP)

### 3.1 Organizations
- Multi-tenant root entity.
- Holds organization profile, contact info, fiscal year settings, timezone (default `Asia/Kathmandu`), and BS/AD display preference.
- One organization → many users, employees, departments, devices.

### 3.2 Employees
- Personal info (name, citizenship no., DOB in AD, contact).
- Employment info (join date, department, designation, employment type — permanent/contract/temporary).
- Status (active, suspended, retired, resigned).
- Links to one or more device user IDs via `employee_device_mappings`.

### 3.3 Departments
- Hierarchical (parent_id, nullable) — supports municipality → ward → section structures.
- Each department may have its own head and attendance rules override.

### 3.4 Designations
- Job titles (e.g., CAO, Section Officer, Sub-Engineer, Teacher).
- Optional pay grade reference (used later by Payroll module).

### 3.5 Devices
- Physical biometric devices (initially ZKTeco).
- Stores: name, model, serial, vendor, IP/port or cloud ID, location, last_online_at, last_sync_at, device_time, status.
- Linked to a department/office location.

### 3.6 Attendance
- **Attendance Events** — raw punches from devices, append-only.
- **Attendance Summaries** — per-employee, per-day computed record (check-in, check-out, hours, late, overtime, status).
- Summaries are regenerated whenever events change or rules change.

### 3.7 Leave Management
- Configurable leave types (annual, sick, casual, special, maternity, etc.).
- Leave balance tracking per employee per fiscal year.
- Application → approval → consumption workflow.
- Approved leave affects attendance summary status (so an absent day with approved sick leave becomes "On Leave — Sick").

### 3.8 Holidays
- National, regional, and organization-specific holidays.
- Stored in AD; displayable in BS.
- Recurring (e.g., Saturday) vs. fixed-date.

### 3.9 Reports
- Daily attendance sheet (per department, per organization).
- Monthly attendance summary.
- Late arrival report.
- Overtime report.
- Leave summary.
- Employee attendance history.
- Exportable to PDF, Excel, CSV.

### 3.10 Corrections
- Workflow for manual attendance adjustments.
- Requester → reviewer → approver chain.
- Every correction logged with reason, attachment, before/after values.
- Approved corrections appear in summaries but the raw event remains untouched (a corrective event is appended).

### 3.11 Audit Logs
- Every state change recorded (who, what, when, before, after, reason).
- Append-only.
- Searchable and exportable.

---

## 4. Future Modules (Phase 2+)

These are explicitly *not* in MVP scope but the architecture must accommodate them:

### 4.1 Payroll
- Consume attendance summaries + leave + overtime to compute salary.
- Tax (1%, 10%, 20%, 30% slabs for Nepal), SSF, PF, CIT deductions.
- Salary slip generation.

### 4.2 Mobile Attendance
- Employee app to punch in/out via mobile.
- Requires fraud controls (device binding, jailbreak detection).

### 4.3 GPS Attendance
- Geofence-based punching for field staff (e.g., sub-engineers, agriculture officers).
- Polygon/radius geofences per work site.

### 4.4 Face Recognition
- Server-side or device-side face matching.
- Pluggable through the same Device Abstraction Layer.

### 4.5 Visitor Management
- Visitor sign-in/out at the office.
- Host approval, badge printing.

### 4.6 Performance Tracking
- KRA/KPI definition.
- Periodic appraisals tied to attendance reliability.

---

## 5. Government & Compliance Requirements

- **Attendance Corrections** must follow a documented approval chain; no silent edits.
- **Approval Workflows** must be configurable per organization (single-step, two-step, department-head-then-admin).
- **Historical Records** must be immutable — raw events are never deleted.
- **Long-Term Retention** — raw events and audit logs must be retained for at least 7 years (configurable per organization to match local policy).
- **Audit Trail** — every modification across all modules logged with actor, timestamp, before/after, IP, user-agent.

---

## 6. Nepal-Specific Requirements

### 6.1 Calendar
- All dates stored as **AD (Gregorian)** in the database.
- All dates displayable in **BS (Bikram Sambat)** at the UI layer.
- A `CalendarService` provides AD↔BS conversion; frontend uses the converted value.
- Fiscal year defaults to Shrawan 1 – Ashad end (mid-July to mid-July).

### 6.2 Timezone
- Default `Asia/Kathmandu` (UTC+5:45).
- All timestamps stored in UTC; converted at the display layer.
- Device timezone is detected and normalized on ingest.

### 6.3 Holidays
- Pre-seeded list of Nepali public holidays (Dashain, Tihar, Chhath, Constitution Day, Loktantra Diwas, Republic Day, etc.).
- Saturday as default weekly holiday (configurable).

### 6.4 Language
- English at MVP.
- i18n scaffolding in place from day one so Nepali (नेपाली) can be added without refactoring.
- Frontend uses `vue-i18n`; backend uses Laravel localization with `lang/en/` and `lang/ne/` directories.

---

## 7. Non-Functional Requirements

| Area | Requirement |
|---|---|
| **Availability** | 99.5% monthly uptime target. Degrades gracefully when devices offline. |
| **Performance** | Daily summary generation for 10,000 employees < 5 minutes. API p95 < 300ms. |
| **Scalability** | Architecture must support 1,000 organizations × 5,000 employees × 5 devices each. |
| **Security** | Sanctum tokens, HTTPS-only, RBAC, audit logs, no biometric template storage. |
| **Offline tolerance** | Devices buffer punches when network is down; full recovery on reconnect. |
| **Internationalization** | English + Nepali (Nepali deferred). |
| **Accessibility** | WCAG 2.1 AA target for employee-facing screens. |
| **Browser support** | Last 2 versions of Chrome, Edge, Firefox, Safari. |

---

## 8. Out of Scope (MVP)

- Native mobile apps.
- Face recognition.
- GPS / geofencing.
- Payroll calculation.
- SMS / IVR notifications.
- Third-party HRIS integration.

These are explicitly deferred to keep MVP shippable, but the data model and service boundaries are designed to accept them without rewrites.
