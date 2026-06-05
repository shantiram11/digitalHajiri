# Features to Consider — e-Hajiri

> A consolidated list of attendance, leave, government, and Nepal-specific features that the system must either support at MVP or be architected to add later without rewrites. Each feature lists its **scope** (MVP / Phase 2 / Future) and **architectural implication** — what the data model or service layer must look like to accommodate it.

---

## 1. Attendance Features

### 1.1 Check In
- **Scope:** MVP
- First inbound punch of the day for an employee.
- Sourced from `attendance_events` where direction is inferred (or explicit if the device supplies it).
- **Implication:** `AttendanceSummary.check_in_at` is computed, never stored directly from devices.

### 1.2 Check Out
- **Scope:** MVP
- Last outbound punch of the day.
- **Implication:** Same as check-in. Pairing logic lives in the `AttendanceCalculator` service.

### 1.3 Multiple Punches
- **Scope:** MVP
- An employee may punch many times a day (lunch, field visit, return). All punches stored.
- Summary uses first-in / last-out by default; configurable to pair-based.
- **Implication:** Raw events are append-only; calculator must handle N punches.

### 1.4 Late Arrival
- **Scope:** MVP
- Configured `office_start_time` + `grace_minutes`.
- If `check_in_at > office_start_time + grace`, flag `is_late = true` and store `late_by_minutes`.
- **Implication:** Rules are per-organization, optionally overridden per-department or per-employee.

### 1.5 Early Departure
- **Scope:** MVP
- Symmetric to late arrival: `check_out_at < office_end_time - grace`.
- **Implication:** Same rule engine.

### 1.6 Overtime
- **Scope:** MVP
- Time worked beyond `office_end_time + ot_threshold_minutes`.
- Optionally requires pre-approval (configurable).
- **Implication:** `AttendanceSummary.overtime_minutes` + `overtime_approved_by` (nullable).

### 1.7 Grace Time
- **Scope:** MVP
- Buffer minutes around office start/end before late/early flags apply.
- **Implication:** Stored on `attendance_rules.grace_minutes`.

### 1.8 Half Day
- **Scope:** MVP
- Triggered when worked hours < `half_day_threshold_minutes` but > `absent_threshold_minutes`.
- **Implication:** Summary status enum includes `half_day`. Thresholds in rules table.

### 1.9 Full Day
- **Scope:** MVP
- Default present state when worked hours ≥ `full_day_threshold_minutes`.
- **Implication:** Summary status `present`.

### 1.10 Night Shift
- **Scope:** Phase 2
- Shift spans midnight — check-in 22:00, check-out 06:00 next calendar day.
- **Implication:** `shifts` table with `crosses_midnight` flag. Summary date is the shift's *start* date. Calculator must group events by shift window, not by calendar day.

### 1.11 Split Shift
- **Scope:** Phase 2
- Two work windows per day (e.g., 09–13 and 15–18). Lunch break is the gap.
- **Implication:** `shift_segments` table with multiple windows per shift.

### 1.12 Configurable Pairing Strategy
- **Scope:** MVP
- `first_in_last_out` (default) vs. `pair_in_out` (sum all in/out pairs).
- **Implication:** Strategy pattern in `AttendanceCalculator`.

### 1.13 Duplicate Punch Detection
- **Scope:** MVP
- Two punches within `duplicate_window_seconds` (default 60) collapsed for calculation but kept in raw events.
- **Implication:** Calculator-side filter; never mutate raw events.

### 1.14 Manual Punch Entry
- **Scope:** MVP
- Officer creates an attendance event manually with `verification_method = manual`.
- Always tied to a correction record.
- **Implication:** Manual events flagged distinctly so reports can show "manual entry" badges.

---

## 2. Leave Features

### 2.1 Annual Leave (Bidaa / Ghar Bidaa)
- **Scope:** MVP
- Accrued annually; configurable accrual rate.
- **Implication:** `leave_balances` table per employee per fiscal year per leave type.

### 2.2 Sick Leave (Bimaari Bidaa)
- **Scope:** MVP
- Often requires medical certificate beyond N days.
- **Implication:** `leave_types.requires_attachment_after_days`.

### 2.3 Casual Leave (Aakasmik Bidaa)
- **Scope:** MVP
- Short-notice leave, capped per month/year.
- **Implication:** Same balance machinery; per-type limits.

### 2.4 Special Leave
- **Scope:** MVP
- Bereavement, marriage, official, study, etc. Configurable by organization.
- **Implication:** `leave_types` is fully data-driven, no hardcoded enums.

### 2.5 Maternity / Paternity Leave
- **Scope:** Phase 2
- Long-duration; consumes separate allocation.
- **Implication:** No special handling needed beyond a leave type with custom rules.

### 2.6 Half-Day Leave
- **Scope:** MVP
- Morning or afternoon only.
- **Implication:** `leave_requests.session` enum: `full | first_half | second_half`.

### 2.7 Leave Balance Tracking
- **Scope:** MVP
- Real-time balance reflecting approved + pending leaves.
- **Implication:** Balance table updated by `LeaveBalanceService` on approval/cancellation.

### 2.8 Approval Workflow
- **Scope:** MVP
- Configurable single-step or multi-step.
- **Implication:** `approval_workflows` table; `approvals` polymorphic table reused for corrections and leaves.

---

## 3. Government & Compliance Features

### 3.1 Attendance Corrections
- **Scope:** MVP
- Forgot punch, device failure, wrong device user — officer files a correction.
- **Implication:** `attendance_corrections` + `attendance_correction_approvals`. Approved corrections insert a corrective event; do not edit raw.

### 3.2 Approval Workflows
- **Scope:** MVP
- Single-step (admin approves directly) or two-step (department head → admin).
- **Implication:** Workflow definition table referenced by leave + correction modules.

### 3.3 Historical Records
- **Scope:** MVP
- Raw events never deleted; summaries are regeneratable but old summaries retained for audit.
- **Implication:** Soft delete is disabled for events; summaries are versioned.

### 3.4 Long-Term Retention
- **Scope:** MVP
- 7-year default retention for events and audit logs.
- **Implication:** Partitioning strategy on `attendance_events` by year for query performance and archival.

### 3.5 Audit Trail
- **Scope:** MVP
- Every state change in every module captured in `audit_logs` (actor, action, model, before, after, IP, UA, timestamp).
- **Implication:** `Auditable` trait on models that need it; Eloquent observers populate `audit_logs`.

### 3.6 Digital Signatures on Reports
- **Scope:** Future
- Approver name + timestamp on PDF outputs.
- **Implication:** PDF generation service receives signer metadata.

### 3.7 Government Report Formats
- **Scope:** Phase 2
- Specific PDF layouts matching DOLIDAR / MOFAGA templates.
- **Implication:** Templated PDF generation (e.g., Blade-based PDF).

---

## 4. Nepal-Specific Features

### 4.1 Bikram Sambat Support
- **Scope:** MVP (display only)
- All dates displayable in BS.
- **Implication:** `CalendarService` with AD↔BS conversion. Frontend component `BsAdDate` wraps any date display.

### 4.2 AD Date Support
- **Scope:** MVP
- All dates stored and queried as AD.
- **Implication:** No special handling — Laravel's `DATE` columns work as-is.

### 4.3 Nepali Public Holidays
- **Scope:** MVP
- Pre-seeded holiday list per fiscal year.
- **Implication:** `holidays` table with `is_national`, `fiscal_year`, recurrence rules.

### 4.4 Local Timezone Support
- **Scope:** MVP
- `Asia/Kathmandu` default; per-organization override possible.
- **Implication:** All timestamps stored UTC; display layer converts using org timezone.

### 4.5 Nepali Fiscal Year
- **Scope:** MVP
- Shrawan 1 – Ashad end.
- **Implication:** `FiscalYearService` produces start/end dates in AD for any BS year.

### 4.6 Nepali Language (नेपाली)
- **Scope:** Phase 2
- Full Nepali UI.
- **Implication:** i18n scaffolding from day one (`vue-i18n`, Laravel `lang/`).

### 4.7 Devanagari Numerals
- **Scope:** Phase 2
- Display option ०१२३४५६७८९ instead of 0123456789.
- **Implication:** Number formatter utility on frontend.

### 4.8 Nepali Citizenship Number Validation
- **Scope:** MVP
- Stored on `employees.citizenship_no`; validated by format (district code + serial).
- **Implication:** Validation rule class `CitizenshipNumber`.

---

## 5. Device & Sync Features

| Feature | Scope | Implication |
|---|---|---|
| Multiple devices per organization | MVP | `devices.organization_id`; one employee may map to multiple devices |
| Multiple device user IDs per employee | MVP | `employee_device_mappings` join table |
| Full sync | MVP | Job pulls all logs since device boot |
| Incremental sync | MVP | Job pulls logs since `devices.last_log_id` |
| Scheduled sync | MVP | Laravel Scheduler every 5 min (configurable) |
| On-demand sync | MVP | Admin button triggers job |
| Offline buffering | MVP | Device-side capability; system tolerates gaps |
| Clock drift detection | MVP | Compare `devices.device_time` to server on each sync |
| Device health monitoring | MVP | `last_online_at`; alert if > threshold |
| Device user provisioning | Phase 2 | Push employee → device via `createUser()` |
| Real-time push from device | Future | Devices that push events via webhook (some ZKTeco models) |

---

## 6. Reporting Features

| Report | Scope | Source |
|---|---|---|
| Daily Attendance Sheet | MVP | `attendance_summaries` |
| Monthly Attendance Summary | MVP | Aggregated summaries |
| Late Arrivals Report | MVP | Summaries where `is_late = true` |
| Overtime Report | MVP | Summaries where `overtime_minutes > 0` |
| Leave Summary | MVP | `leave_requests` + `leave_balances` |
| Employee Attendance History | MVP | Summaries per employee |
| Department Comparison | Phase 2 | Aggregated summaries grouped by department |
| Custom Date Range Report | MVP | Parameterized |
| Export PDF | MVP | Server-side PDF (DomPDF or Browsershot) |
| Export Excel | MVP | Laravel Excel |
| Export CSV | MVP | Stream response |
| Scheduled Email Reports | Phase 2 | Scheduler + Mail |

> **Architectural rule:** Reports never compute from `attendance_events` directly. They always read from `attendance_summaries`. Summaries are the materialized view.

---

## 7. Notification Features

| Channel | Scope |
|---|---|
| In-app notifications | MVP |
| Email | MVP |
| SMS | Future |
| Push (mobile) | Future |

Triggers: leave approved/rejected, correction approved/rejected, device offline, monthly report ready.

---

## 8. Administrative Features

- Organization onboarding wizard (Phase 2)
- Bulk employee import via Excel (MVP)
- Bulk device-user mapping (MVP)
- Holiday calendar import (MVP)
- Backup & restore (operations concern; not in-app at MVP)
- Impersonation by Super Admin (MVP, logged)

---

## 9. Security Features

- Sanctum token authentication (MVP)
- Role-based access control (MVP)
- Per-organization data isolation (MVP)
- Password policies (MVP)
- 2FA via TOTP (Phase 2)
- IP allowlisting per organization (Phase 2)
- Comprehensive audit log (MVP)
- No biometric template storage on server (MVP — architectural rule)

---

## 10. Future Modules Summary

These are listed here so their data and service touchpoints are reserved during MVP scaffolding:

- **Payroll** — consumes summaries; needs `pay_grades`, `salary_components`, `payslips`.
- **Mobile Attendance** — adds `mobile_devices`, `mobile_punches` (which produce `attendance_events` with `verification_method = mobile`).
- **GPS Attendance** — adds `geofences`, `gps_punches`.
- **Face Recognition** — pluggable via the Device Abstraction Layer.
- **Visitor Management** — adds `visitors`, `visit_logs`.
- **Performance Tracking** — adds `kpis`, `appraisals`.

All of these slot into the existing event-summary-rule architecture without rewrites.
