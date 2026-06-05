# Authorization — e-Hajiri

> Permission-checking touches every request. This document defines how roles and permissions are modeled, how the same `$user->can('attendance.approve')` call yields the right answer in every tenant, how the system stays fast under load, and the explicit reasoning behind every customization to Spatie's library.

---

## 1. Goals & non-goals

### Goals
- Tenant-aware roles: the same slug (`organization-admin`) can be assigned to different users in different organizations independently.
- System-level roles (super admin, platform support, system auditor) that operate across all tenants.
- Global, reusable permission catalog — a permission name means the same thing everywhere.
- User-specific permission overrides: grant a single permission to a user without inventing a one-off role.
- Cached resolution so authorization checks do not become a database hot spot.
- Future-proof: every Phase 5+ module (Attendance, Leave, Reports, Payroll, Devices, Mobile, Face Recognition, Visitor, Analytics) adds permissions by writing one seeder row, not by changing the resolver.

### Non-goals (at MVP)
- Attribute-based access control (record-level conditions encoded in permission strings). We use **Policies** for the record-level "can this user approve *this* request" decisions; permissions answer the broader "can this user approve at all."
- Field-level permissions. Not in this iteration.
- Dynamic permission strings (e.g. `attendance.approve:department-{id}`). Adds complexity the MVP does not need.

---

## 2. The library choice — Spatie, customized

We use `spatie/laravel-permission` because it solves 80 % of the problem out of the box: model traits, role/permission tables, direct user permissions, command-level caching, Blade/Vue directives.

The 20 % it does not solve is the part this document is about: **tenant-aware roles**.

### 2.1 What Spatie gives us (kept verbatim)
- `permissions` table (id, name, guard_name, timestamps).
- `roles` table (id, name, guard_name, timestamps).
- `role_has_permissions` pivot (role_id, permission_id).
- `model_has_permissions` pivot (permission_id, model_type, model_id) — this is where **direct user permissions** live.
- `model_has_roles` pivot (role_id, model_type, model_id) — role assignments.
- `HasRoles` trait on the User model.
- `$user->can('perm')`, `$user->hasRole('slug')`, `$role->givePermissionTo('perm')` API.
- Cache layer (configurable store + TTL).

### 2.2 What we add (custom)
- `roles.organization_id` (nullable). `null` = system role, value = org-scoped role.
- `role_has_permissions` unchanged but reads use the **team key**.
- `model_has_roles.team_id` (aliased to `organization_id` in config) — Spatie's built-in **teams** feature, repurposed for our tenants.
- `model_has_permissions.team_id` — same, for direct user permissions per organization.
- `PermissionResolver` service that wraps Spatie's checks with the multi-org membership and impersonation rules.
- `OrganizationContext`-aware Gate registration so `$user->can('perm')` works without callers passing the org in.

### 2.3 Why teams, why not multi-guard

Spatie supports two patterns for partitioning roles:

| Pattern | Verdict | Reason |
|---|---|---|
| **Teams** (one guard, role.team_id partitions) | ✅ chosen | One auth guard, one user table, one Sanctum stack. The team key is the organization id. Spatie's own resolver respects `team_id` when teams are enabled. Adds one column; reuses the entire ecosystem. |
| Multi-guard (separate guard per org) | ❌ rejected | Requires per-tenant Auth configuration, breaks Sanctum's single-guard assumption, and explodes when a user belongs to N organizations. |
| Single-tenant Spatie + own resolver | ❌ rejected | Loses cache layer, loses `HasRoles` trait, loses ecosystem (Blade/Vue directives, Filament integrations). Wheel-reinvention without justification. |

> Spatie's "teams" feature is identical in shape to what we need; the team is simply called "organization" in our domain language. Aliasing is purely linguistic.

---

## 3. Role strategy

### 3.1 Two role tiers

```
                ┌──────────────────────────┐
                │   System Roles           │
                │   organization_id = NULL │
                │                          │
                │ - super-admin            │
                │ - platform-support       │
                │ - system-auditor         │
                └────────────┬─────────────┘
                             │
                             │ assigned with model_has_roles.team_id = NULL
                             ▼
                ┌──────────────────────────┐
                │   User                   │
                └────────────┬─────────────┘
                             │
                             │ assigned with model_has_roles.team_id = <org id>
                             ▼
                ┌──────────────────────────┐
                │  Organization Roles      │
                │  organization_id = <id>  │
                │                          │
                │ - organization-admin     │
                │ - attendance-manager     │
                │ - hr-officer             │
                │ - department-head        │
                │ - employee               │
                │ + any custom org role    │
                └──────────────────────────┘
```

### 3.2 The role-template pattern

Each new organization gets its **own row** for each default role — *not* a shared one. Three reasons:

1. Spatie's cache key includes `team_id` and `role_id` separately, so sharing a role row across orgs would make the cache shape wrong.
2. Organizations may rename or extend their roles ("Senior Attendance Manager") without affecting other tenants.
3. Future Phase-2 RBAC features (e.g. per-org permission overrides on a role) need per-org rows to write into.

When an organization is created, `OrganizationProvisioner::seedDefaultRoles(Organization)` clones the role template into that org. The template lives in `database/seeders/data/organization_roles.php` so it is version-controlled and review-friendly.

### 3.3 Role precedence

A user may hold:
- Zero or one system role.
- Zero or many organization roles **within the same org** (we do not forbid it; e.g. "department-head" + "attendance-manager").

Effective permissions in a given org = union of:
- System role's permissions (applies cross-tenant).
- All org roles' permissions in *this* org.
- Direct permissions granted to the user in *this* org.

> Direct permissions are **never** "withdrawals from a role." If you need to subtract, create a more restrictive role. Otherwise the resolver becomes non-monotone and impossible to cache predictably.

---

## 4. Permission strategy

### 4.1 Permissions are global

The same `attendance.approve` string is the same right regardless of org. This matches Spatie's `permissions` table shape and lets us seed once and never duplicate.

### 4.2 Naming convention

`module.action` lowercase, dot-separated:
- `module`: one of the 12 domain modules (`attendance`, `leave`, `device`, `employee`, `report`, `organization`, `user`, `role`, `permission`, `audit`, `correction`, `holiday`, `system`).
- `action`: a verb or noun in present tense (`view`, `create`, `edit`, `delete`, `approve`, `manage`, `export`).

Subscoped actions append a colon-qualifier *only when distinct from the base verb*:
- `attendance.approve` (approve an attendance entry)
- `attendance.correct` (file a correction; distinct from approving one)
- `leave.approve:own-department` (Phase 2 — not in MVP catalog)

### 4.3 Complete catalog (MVP)

| Module | Permission | Description |
|---|---|---|
| **attendance** | `attendance.view` | View attendance summaries and events for accessible employees. |
|  | `attendance.create` | Manually create an attendance event (always tied to a correction). |
|  | `attendance.edit` | Edit the metadata of a summary (does not mutate raw events). |
|  | `attendance.delete` | Soft-archive a summary (raw events are never deletable). |
|  | `attendance.approve` | Approve overtime, correction-derived events, or ad-hoc rules. |
|  | `attendance.correct` | File an attendance correction request. |
|  | `attendance.sync` | Trigger an on-demand device sync. |
| **leave** | `leave.view` | View leave requests and balances. |
|  | `leave.create` | Apply for leave (own profile by default). |
|  | `leave.edit` | Edit own pending leave; HR can edit any. |
|  | `leave.delete` | Cancel a leave request. |
|  | `leave.approve` | Approve or reject a leave request. |
|  | `leave.manage-balances` | Adjust leave balances (HR / admin). |
| **device** | `device.view` | View device list, status, sync history. |
|  | `device.manage` | Create, edit, delete devices and mappings. |
|  | `device.sync` | Trigger sync for any device in the org. |
|  | `device.monitor` | Receive device health alerts. |
| **employee** | `employee.view` | View employee directory. |
|  | `employee.create` | Add an employee. |
|  | `employee.edit` | Edit employee profile. |
|  | `employee.delete` | Soft-delete (resign) an employee. |
|  | `employee.import` | Bulk import employees from Excel. |
| **department** | `department.view` | View department tree. |
|  | `department.manage` | Create, edit, reorganize departments. |
| **designation** | `designation.view` | View designations. |
|  | `designation.manage` | Manage designations. |
| **holiday** | `holiday.view` | View holiday calendar. |
|  | `holiday.manage` | Add or remove holidays for the org. |
| **correction** | `correction.view` | View attendance corrections. |
|  | `correction.approve` | Decide on correction requests. |
| **report** | `report.view` | View reports in-app. |
|  | `report.export` | Download reports as PDF / Excel / CSV. |
|  | `report.schedule` | Schedule report emails (Phase 2 — reserved). |
| **organization** | `organization.view` | View own organization profile. |
|  | `organization.manage` | Edit organization settings. |
| **user** | `user.view` | View users in the org. |
|  | `user.invite` | Send invitations. |
|  | `user.manage` | Edit, suspend, or remove users from the org. |
|  | `user.impersonate` | Impersonate another user (super admin only, granted on the role not on users). |
| **role** | `role.view` | View roles. |
|  | `role.manage` | Create, edit, delete org roles. |
| **permission** | `permission.view` | View permission catalog and assignments. |
|  | `permission.assign` | Grant or revoke direct user permissions. |
| **audit** | `audit.view` | View audit log. |
|  | `audit.export` | Export audit log. |
| **system** | `system.manage` | Platform-wide settings (super admin only). |
|  | `system.tenants.manage` | Create, suspend, hard-delete organizations. |
|  | `system.impersonate` | Impersonate anyone. |
|  | `system.audit.read` | Cross-tenant audit log read (system auditor). |

> Total: 41 permissions at MVP. The catalog grows by adding seeder rows; the resolver and middleware never change.

---

## 5. Default role → permission matrix

> Permissions not listed for a role are **denied** for that role. Direct user permission grants override on a per-user basis.

### 5.1 System roles

| Permission | super-admin | platform-support | system-auditor |
|---|:-:|:-:|:-:|
| `system.manage` | ✅ | | |
| `system.tenants.manage` | ✅ | ✅ | |
| `system.impersonate` | ✅ | ✅ | |
| `system.audit.read` | ✅ | ✅ | ✅ |
| `audit.view` (any tenant) | ✅ | ✅ | ✅ |
| `audit.export` (any tenant) | ✅ | | ✅ |
| All `*.view` (any tenant, read-only) | ✅ | ✅ | ✅ |
| All other `*.manage` / mutations | ✅ | | |

**Reasoning.** *Super Admin* is unrestricted by design and is the break-glass role. *Platform Support* can read everything and impersonate to help customers, but cannot change tenant data unilaterally. *System Auditor* is the read-only and export role for compliance audits; never has impersonation.

### 5.2 Organization roles

| Permission | organization-admin | attendance-manager | hr-officer | department-head | employee |
|---|:-:|:-:|:-:|:-:|:-:|
| `organization.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `organization.manage` | ✅ | | | | |
| `user.view` | ✅ | ✅ | ✅ | ✅ | |
| `user.invite` | ✅ | | ✅ | | |
| `user.manage` | ✅ | | ✅ | | |
| `role.view` | ✅ | ✅ | ✅ | | |
| `role.manage` | ✅ | | | | |
| `permission.view` | ✅ | ✅ | ✅ | | |
| `permission.assign` | ✅ | | | | |
| `employee.view` | ✅ | ✅ | ✅ | ✅ | |
| `employee.create` | ✅ | | ✅ | | |
| `employee.edit` | ✅ | | ✅ | | |
| `employee.delete` | ✅ | | ✅ | | |
| `employee.import` | ✅ | | ✅ | | |
| `department.view` | ✅ | ✅ | ✅ | ✅ | |
| `department.manage` | ✅ | | ✅ | | |
| `designation.view` | ✅ | ✅ | ✅ | ✅ | |
| `designation.manage` | ✅ | | ✅ | | |
| `device.view` | ✅ | ✅ | | | |
| `device.manage` | ✅ | | | | |
| `device.sync` | ✅ | ✅ | | | |
| `device.monitor` | ✅ | ✅ | | | |
| `attendance.view` | ✅ | ✅ | ✅ | ✅ | own |
| `attendance.create` | ✅ | ✅ | | | |
| `attendance.edit` | ✅ | ✅ | | | |
| `attendance.delete` | ✅ | | | | |
| `attendance.approve` | ✅ | ✅ | | dept | |
| `attendance.correct` | ✅ | ✅ | | | own |
| `attendance.sync` | ✅ | ✅ | | | |
| `leave.view` | ✅ | ✅ | ✅ | dept | own |
| `leave.create` | ✅ | | ✅ | | ✅ |
| `leave.edit` | ✅ | | ✅ | | own |
| `leave.delete` | ✅ | | ✅ | | own |
| `leave.approve` | ✅ | | ✅ | dept | |
| `leave.manage-balances` | ✅ | | ✅ | | |
| `holiday.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `holiday.manage` | ✅ | | ✅ | | |
| `correction.view` | ✅ | ✅ | ✅ | dept | own |
| `correction.approve` | ✅ | ✅ | | dept | |
| `report.view` | ✅ | ✅ | ✅ | dept | own |
| `report.export` | ✅ | ✅ | ✅ | | |
| `audit.view` | ✅ | | ✅ | | |
| `audit.export` | ✅ | | ✅ | | |

> "own" / "dept" cells are **not encoded as separate permissions** — they are policy decisions made in `EmployeePolicy::view`, `LeavePolicy::approve`, etc. The permission gates the *broad capability*; the policy gates the *specific record*.

---

## 6. Tenant isolation strategy

### 6.1 Three layers (defense in depth)

1. **Schema.** Every tenant-bound table has `organization_id`. Cross-tenant joins require explicit `withoutGlobalScope`.
2. **Global scope.** `OrganizationScope` applies `where organization_id = current` on every Eloquent query when context is set.
3. **Membership check.** `EnsureOrganizationContext` middleware verifies that the authenticated user is in `organization_user` for the resolved org. Failure returns 409 *Conflict*. Super admins are exempt; their access is gated by `system.*` permissions and logged.

### 6.2 Role assignment is per-tenant

When `Spatie\Permission\Models\Role::assignRole($user)` is called, our wrapper requires the **current organization context** to be set. The team_id written to `model_has_roles` is the organization id. Assigning the same role to the same user in a different org creates a separate row — by design.

### 6.3 Direct permissions are per-tenant

`$user->givePermissionTo('attendance.approve')` writes a row with `team_id = current_org_id`. Querying `$user->getDirectPermissions()` in another org returns nothing for that grant.

### 6.4 System roles bypass the team

System roles have `organization_id = null` in `roles`, and `model_has_roles.team_id = null` for their assignments. The Spatie team-aware resolver naturally treats these as "applies in every team," which is the system-role semantics we want.

---

## 7. Permission resolution engine

### 7.1 The contract

```php
$user->can('attendance.approve');          // uses current org context
$user->canIn($org, 'attendance.approve');  // explicit org
$user->permissionsIn($org): Collection;    // for the SPA permission store
```

### 7.2 Resolution order

For `can($ability)` in organization context `$org`:

1. If `$user` has a **system role** that includes `$ability` → **allow**.
2. If `$user` is a member of `$org` AND has a **direct permission** in `$org` for `$ability` → **allow**.
3. If `$user` is a member of `$org` AND any of their **org roles** in `$org` include `$ability` → **allow**.
4. Otherwise → **deny**.

### 7.3 Implementation — the `PermissionResolver` service

`PermissionResolver` lives in `App\Domain\Identity\Services`. It:
- Wraps `Spatie\Permission\PermissionRegistrar::setPermissionsTeamId($org->id)` for the duration of the check (so Spatie's own evaluator scopes correctly).
- Adds the system-role check **before** Spatie runs, short-circuiting on allow.
- Caches per `(user_id, org_id)` for the request lifetime; invalidated on assignment/revocation events.

### 7.4 Gate wiring

We register a `Gate::before(...)` callback in `AuthServiceProvider`:

```php
Gate::before(fn (User $user, string $ability) =>
    app(PermissionResolver::class)->resolve($user, $ability) ? true : null
);
```

The callback returns `true` on allow (short-circuits the rest of the Gate), `null` on no-opinion (lets policies decide on a per-record basis). Returning `false` would forbid policies from ever granting access, which is wrong — we always defer to policies for record-level decisions.

### 7.5 The two checks together

```
$user->can('leave.approve', $leaveRequest)
        │
        ▼
Gate::before → PermissionResolver
        │
        ├─ user has system role with leave.approve?         → ALLOW
        ├─ user has direct leave.approve in current org?    → ALLOW
        ├─ user has role with leave.approve in current org? → fall through to policy
        │                                                     ▼
        │                                          LeavePolicy::approve($user, $leaveRequest)
        │                                                     │
        │                                          (is the request in my department?
        │                                           is the requester active?
        │                                           am I not the requester?)
        └─ none of the above                                 → DENY
```

This split keeps permissions answering "can you do this kind of thing at all" and policies answering "can you do this *to this record*." Both must agree.

---

## 8. Caching strategy

### 8.1 What we cache, where, and for how long

| Cache | Key | TTL | Store | Reason |
|---|---|---|---|---|
| Spatie's built-in permission catalog | `spatie.permission.cache` | 24 h | Redis (config) | Avoid querying `permissions` table on every request. Built-in. |
| Per-user role/permission resolution | `auth:user:{id}:org:{orgId}:perms` | 5 min | Redis | The hot path. `$user->canIn($org, $perm)` reduces to a `SISMEMBER` on a precomputed set. |
| Membership list for SPA | `auth:user:{id}:memberships` | 5 min | Redis | The login + org-switcher response. |

### 8.2 Invalidation

Cache invalidation is **event-driven**:

| Event | Invalidates |
|---|---|
| `RoleAssigned` / `RoleRevoked` (Spatie events) | the user's per-org perms cache for the affected org |
| `PermissionAssigned` / `PermissionRevoked` | the user's per-org perms cache for the affected org |
| `Role` model `updated` (permission set changed) | every user holding that role — wildcard delete pattern `auth:user:*:org:{orgId}:perms` if Redis store, otherwise flush of `auth:user:*` |
| `OrganizationUser` `created` / `deleted` (membership) | the user's memberships cache + perms cache for the affected org |
| Password change / forced logout | all `auth:user:{id}:*` keys |

`Listeners\InvalidatePermissionCache` and `Listeners\InvalidateMembershipCache` handle these. Both implement `ShouldQueue` so user-facing requests are not delayed by cache work.

### 8.3 Why 5 minutes and not 24 hours

A revoked permission must take effect quickly enough that a fired employee cannot continue making changes. 5 minutes is the worst case if the event-driven invalidation misses (e.g. Redis flushed unexpectedly). For most flows the change is visible immediately because the resolver listens for the matching event and busts the key.

### 8.4 Why not session-level caching

Sessions are per-tab; caches are per-user. Putting permissions in the session means a user with two tabs sees a stale permission set in one of them after a role change. Redis-backed user-level cache + event invalidation gives one consistent view across all sessions.

---

## 9. Membership architecture

```
organizations            users
─────────────            ─────
id                       id
...                      organization_id  (DEFAULT/primary, nullable)
                         ...

                ┌────────────────────────┐
                │  organization_user     │
                │  ────────────────────  │
                │  id                    │
                │  organization_id  FK   │
                │  user_id          FK   │
                │  status: active|invited|suspended
                │  joined_at            │
                │  invited_by_user_id   │
                │  metadata (json)      │
                └────────────────────────┘
```

### 9.1 Why a join table when users.organization_id already exists

`users.organization_id` is the *default* the SPA uses to choose an org on login. The join table is the *authority* on whether a user can access any given org.

This split is deliberate:
- Removing a user from one of their organizations should not require touching the `users` row.
- A user may have no memberships at a moment (e.g. all revoked); their account still exists for password reset and audit purposes.
- A future "personal workspace" pattern (Phase 2) where a user owns an org pivots cleanly on the join table.

### 9.2 Removing a membership

Soft-paradigm: the row is deleted but the audit log preserves it, and a `MembershipRevoked` event is fired. The user's role assignments in that org (in `model_has_roles` with `team_id = org_id`) are cascaded.

### 9.3 Status enum

| Status | Meaning |
|---|---|
| `invited` | Invitation accepted but org admin has not activated yet (Phase-2 onboarding workflows). |
| `active` | Full member. |
| `suspended` | Temporarily blocked. Cannot access this org; cannot be reactivated except by admin. |

Suspended users skip every permission check in this org regardless of roles.

---

## 10. Invitations

### 10.1 Flow

```
Org Admin                  Server                          Invitee
   │                          │                              │
   │ POST /invitations        │                              │
   │ { email, role_id }       │                              │
   ├─────────────────────────►│ generate signed token         │
   │                          │ write organization_invitations│
   │                          │ send InvitationMail           │
   │                          │ ── audit_log: invitation.sent │
   │                          │─────────────────────────────► │ (email link)
   │                          │                              │
   │                          │                              │
   │                          │  GET /invitations/{token}     │
   │                          │ ◄────────────────────────────│
   │                          │ 200 with invitation summary   │
   │                          │                              │
   │                          │  POST /invitations/accept     │
   │                          │  { token, password? }         │
   │                          │ ◄────────────────────────────│
   │                          │ verify signature + not expired│
   │                          │ find or create User           │
   │                          │ create organization_user row  │
   │                          │ assignRole(role_id)           │
   │                          │ mark invitation accepted      │
   │                          │ ── audit_log: invitation.accepted
   │                          │ ── event: MembershipCreated   │
   │                          │ 200 { redirect: /login }      │
   │                          │─────────────────────────────► │
```

### 10.2 Schema

```
organization_invitations
────────────────────────
id
organization_id    FK
invited_by_user_id FK
email
role_id            FK -> roles  (the org role to assign on accept)
token              char(64)  unique  (signed)
expires_at         timestamp (default: now() + 7 days)
accepted_at        timestamp nullable
revoked_at         timestamp nullable
created_at, updated_at
```

### 10.3 Constraints
- Token is a cryptographically random 64-char string, stored as plaintext (it is single-use and short-lived). The URL is signed by Laravel's signed URL machinery so tampering invalidates it.
- An email can have at most one **active** (`accepted_at IS NULL AND revoked_at IS NULL AND expires_at > now()`) invitation per organization at a time. The DB enforces this via a partial unique index (or by application logic on SQLite at MVP).
- Accepting an invitation when already a member is a no-op + 200 (idempotency).
- Revoking expired invitations is the org admin's right, even though they expire naturally.

### 10.4 Why signed URLs *and* a DB row

The signed URL prevents tampering, but does not provide single-use semantics — Laravel signed URLs are stateless. The DB row enforces single-use and expiry beyond what the signature can express. Together they give cryptographic non-forgeability + revocability + idempotency.

---

## 11. Middleware catalog

| Alias | Class | Purpose |
|---|---|---|
| `organization` | `EnsureOrganizationContext` | Resolve current org, verify membership, set context. Returns 409 on non-member, 401 on no auth. |
| `permission:slug` | `EnsurePermission` | Returns 403 if `$user->can($slug)` is false. Multiple permissions: `permission:role.view,role.manage` = ANY-of; use `permission.all:` to require ALL. |
| `system-role:slug` | `EnsureSystemRole` | Returns 403 unless user holds at least one of the listed system roles. Used on `/api/v1/admin/*` routes. |
| `member-of:org` | (composable) | Internal use; `EnsureOrganizationContext` already covers this for the active org. |

> Role-based middleware (`role:slug`) deliberately does **not** exist. Routes are gated by permissions, not roles. Roles bundle permissions; gating by role would silently bypass per-org customisations and direct grants.

---

## 12. Frontend authorization model

### 12.1 The permission store

`stores/permissions.ts` (Pinia) holds, for the current org:
- `permissions: Set<string>` — the *resolved set* from `/api/v1/auth/me`.
- `systemRoles: string[]` — for super-admin badges and admin-area visibility.
- `orgRoles: string[]` — for display only; not used in checks.

### 12.2 Checking client-side

```ts
const { can, canAny, canAll } = usePermissions();
if (can('leave.approve')) { ... }
```

Or directively:
```vue
<button v-can="'leave.approve'">Approve</button>
<button v-can.any="['leave.approve', 'leave.manage-balances']">…</button>
```

The frontend check is **only for UX** (hiding buttons). The server re-checks on every request. Anyone who tampers with the store sees disabled buttons spring to life and then gets 403 from the server. This is intentional — the trust boundary is the API.

### 12.3 Org switcher

When the user switches org via the UI:
1. `POST /api/v1/auth/select-organization` sets `current_organization_id` in the session.
2. The SPA re-fetches `/api/v1/auth/me` and replaces the permission set.
3. Route guards re-evaluate.

### 12.4 Route guards

Routes declare required permissions in their `meta`:
```ts
{ path: '/devices', component: DeviceList, meta: { permission: 'device.view' } }
```

The global `router.beforeEach` (in `resources/js/router/index.ts`) consults the permission store and redirects to `/forbidden` when the permission is absent.

### 12.5 Navigation filtering

The sidebar consumes the same store: every menu item declares a permission, items the user does not have are hidden. No conditional `v-if` clutter at every call site.

---

## 13. Future scalability

The architecture absorbs the following without changes to the resolver, middleware, or schema:

| Future module | What it adds | Notes |
|---|---|---|
| **Payroll** | `payroll.*` permissions, a `payroll-officer` org role. | Seeder rows only. |
| **Mobile attendance** | `mobile.punch`, `mobile.device.manage`. | Permissions; mobile devices use bearer tokens with `org:<id>` ability. |
| **GPS attendance** | `gps.geofence.manage`. | Permissions only. |
| **Face recognition** | Same as device drivers. | No new identity concerns. |
| **Visitor management** | `visitor.*` permissions. | Visitor records carry their own org id. |
| **Analytics dashboards** | `analytics.view` etc. | Permissions; data is summary-derived. |
| **Per-org permission overrides on a role** | A `role_has_permissions.team_id` column (already supported by Spatie teams). | Migration only. |
| **Field-level permissions** | A new `field_permissions` table consumed by a Phase-2 `FieldGate`. | Resolver unaffected. |
| **External identity (SSO)** | An `oauth_identities` table; auth controller adapts. | Authorization unchanged. |

---

## 14. Why these decisions — one paragraph each

- **Spatie + teams over a hand-rolled resolver** because Spatie has been hammered by thousands of projects and the team feature is the right shape; the only real custom work is system roles and the resolver wrapper.
- **System-role short-circuit before Spatie** because system roles must override everything, including suspended-membership states, and that's the easiest place to enforce it.
- **Permissions are global, roles are tenant-scoped** because permissions describe *capabilities* (stable, slowly-changing vocabulary) and roles describe *bundles* (tenant-specific, fast-changing). Conflating them creates an N×M explosion every time a new module ships.
- **Membership table separate from `users.organization_id`** because the default is a UX concern; the membership is the authority. Mixing them produces the bug "I removed myself from my own org and now I can't log in."
- **Event-driven cache invalidation, not TTL-only** because a fired employee needs to lose access *now*, not in five minutes. TTL is the safety net for missed events.
- **Policies for record-level rules, permissions for capabilities** because some authorization rules ("this leave is in my department") are facts about the record, not the user, and policies are the right vehicle.
- **No `role:` middleware** because gating by role is a known anti-pattern — it creates implicit dependencies on which permissions a role happens to have today.
- **Signed-URL invitations + DB row** because either alone is weaker: signature is non-revocable, DB-row alone is forgeable. Combined they cover both.
- **Cache keys include the org id** because that is what makes the cache safe to share across tabs and devices for a single user, and it is what makes per-org revocation an O(1) delete.
