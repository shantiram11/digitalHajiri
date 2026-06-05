# Authentication — e-Hajiri

> Authentication is the front door of every other security control. This document defines how identities are proven, how sessions are managed, how multi-tenant context is resolved at login time, and the explicit reasoning behind every choice so the system can be audited.

---

## 1. Goals & non-goals

### Goals
- Production-ready login for the SPA against the Laravel API.
- Multi-tenant aware: a user may belong to one or many organizations and must operate within exactly one at a time per request.
- Future-ready: invitation flow, password reset, email verification, native mobile, third-party integrations.
- Auditable: every login, logout, failure, impersonation, password change, and session revocation is recorded in `audit_logs`.

### Non-goals (at MVP)
- Federated identity (SAML, OIDC, OAuth via Google/Microsoft). The architecture leaves room; we do not ship it.
- Username-based login. Email + password only.
- Magic-link login. Reserved for the mobile app, post-MVP.

---

## 2. Why Sanctum, not JWT or Passport

| Option | Verdict | Reason |
|---|---|---|
| **Sanctum (SPA + tokens)** | ✅ chosen | First-party Laravel package, two modes in one library: HttpOnly session cookies for our SPA, opaque bearer tokens for the future mobile app and external integrations. No JWT key management, no public-key rotation, no Passport's OAuth server complexity. |
| JWT | ❌ rejected | Self-contained tokens are great for stateless microservices but cumbersome to revoke (revocation list / short expiry + refresh dance), and the SPA gains no benefit. Worse: storing JWTs in `localStorage` is the standard XSS exfiltration target. We would have to do exactly what Sanctum already gives us. |
| Passport (OAuth2 server) | ❌ rejected | Designed for "be your own identity provider for third parties." We are an end application, not an identity provider. Reserved for when a future requirement explicitly demands third-party API authorization. |

> See `docs/architecture.md` §8 for the broader security strategy this slots into.

---

## 3. The two authentication modes Sanctum gives us

### 3.1 SPA mode — for our Vue 3 app
1. The browser hits `GET /sanctum/csrf-cookie` once at app boot. Sanctum sets an `XSRF-TOKEN` cookie.
2. The SPA reads `XSRF-TOKEN` (it is not HttpOnly, by design — it must be readable by JS) and sends it as the `X-XSRF-TOKEN` header on every state-changing request.
3. The SPA `POST /api/v1/auth/login` with email/password. On success Laravel sets the `digitalhajiri_session` cookie (HttpOnly, SameSite=Lax, Secure in production).
4. All subsequent requests carry the session cookie automatically. Sanctum's `EnsureFrontendRequestsAreStateful` middleware (already prepended in `bootstrap/app.php`) recognises requests from configured stateful domains and authenticates via the session guard.
5. `POST /api/v1/auth/logout` invalidates the session and clears the cookie.

### 3.2 Bearer-token mode — for future mobile + integrations
1. Client `POST /api/v1/auth/token` with email/password + device-name. Server returns `{ token, abilities }`.
2. Client sends `Authorization: Bearer <token>` on every request.
3. Tokens carry **abilities** (scopes) — e.g., `attendance.read`, `device.sync` — so an integration token is limited to what it needs.
4. Tokens are revocable individually (`/api/v1/auth/tokens/{id}`) and globally on password change.

> The SPA never receives a bearer token. The mobile app never receives a session cookie. The two modes do not mix.

---

## 4. Authentication flows

### 4.1 Login (SPA)

```
Browser                       Vite/Nginx                  Laravel
   │                              │                           │
   │ GET /sanctum/csrf-cookie     │                           │
   ├─────────────────────────────►│ ─────────────────────────►│
   │                              │ 204, Set-Cookie XSRF-TOKEN│
   │◄─────────────────────────────┤◄──────────────────────────┤
   │                              │                           │
   │ POST /api/v1/auth/login      │                           │
   │   X-XSRF-TOKEN, email, pwd   │                           │
   ├─────────────────────────────►│ ─────────────────────────►│  validate
   │                              │                           │  RateLimiter::attempt
   │                              │                           │  Auth::attempt
   │                              │                           │  regenerate session
   │                              │                           │  audit_log: login
   │                              │ 200 { user, memberships } │
   │◄─────────────────────────────┤◄──────────────────────────┤
   │ Set-Cookie digitalhajiri_session                         │
```

On success the response body carries:
```json
{
  "data": {
    "user": { "id": 12, "name": "Ram Bahadur", "email": "ram@muni.gov.np" },
    "memberships": [
      { "organization_id": 4, "organization_name": "Kathmandu Metropolitan", "roles": ["organization-admin"] },
      { "organization_id": 9, "organization_name": "Lalitpur Sub-Metropolitan", "roles": ["attendance-manager"] }
    ],
    "default_organization_id": 4
  }
}
```

The SPA stores nothing sensitive — only the membership list, used for the org-switcher UI and route guards. Real authority is server-side, re-checked on every request.

### 4.2 Logout
- `POST /api/v1/auth/logout`
- Invalidates the current session, regenerates the CSRF token, clears the membership cache for that user, writes `logout` to `audit_logs`.

### 4.3 Session lifecycle
- **Inactivity timeout**: `SESSION_LIFETIME=120` (minutes) by default — configurable per environment.
- **Sliding**: each authenticated request refreshes the cookie expiry.
- **Hard expiry**: 30 days regardless of activity (`SESSION_LIFETIME` does not override this; it's a separate hard cap enforced by `session.expire_on_close` + a `last_login_at` check on resolution).
- **Single sign-on across tabs**: cookies are domain-scoped; opening a new tab inherits the session.

### 4.4 Password reset
- `POST /api/v1/auth/forgot-password` with email. Always returns 200 (no enumeration), enqueues a `ResetPasswordNotification` if the email exists.
- The notification email contains a signed URL valid for 60 minutes pointing at the SPA's `/reset-password?token=...&email=...`.
- The SPA `POST /api/v1/auth/reset-password` with token + email + new password.
- On success: password updated, **all existing tokens revoked** and **all sessions invalidated** for that user, audit log entry.

### 4.5 Email verification (Phase 2 hardening)
- `MustVerifyEmail` contract on User. Verification gate disabled at MVP but the column already exists (`email_verified_at`).
- Verification link uses signed URLs with 60-minute TTL.

### 4.6 Invitation acceptance (full detail in `docs/authorization.md` §10)
- Organization Admin sends invitation → email contains a signed `/accept-invitation?token=...` link.
- New user lands on the SPA → if they already have an account, they confirm and the membership is created; if they don't, they set a password and a User is created + the membership added.

---

## 5. Security design

### 5.1 Session cookies
| Attribute | Value | Reason |
|---|---|---|
| `HttpOnly` | `true` | JavaScript cannot read or steal the session cookie — defense against XSS. |
| `Secure` | `true` in production | Cookie only travels over HTTPS. |
| `SameSite` | `Lax` | CSRF defense in depth; the SPA shares the root domain so `Lax` does not break it. We do not need `Strict` because OAuth callbacks (not in scope) would break under it. |
| `Domain` | leading-dot for SPA + API on same registrable domain | One cookie covers both. |
| `Path` | `/` | Default. |

### 5.2 CSRF
- Enforced for all state-changing methods on stateful (session-cookie) requests via `EnsureFrontendRequestsAreStateful` + Laravel's `VerifyCsrfToken`.
- Bearer-token requests are exempt from CSRF (they have no ambient credentials).
- `XSRF-TOKEN` cookie + `X-XSRF-TOKEN` header is the double-submit pattern; Axios does this automatically when `withCredentials: true`.

### 5.3 Rate limiting
| Endpoint | Limit | Window | Rationale |
|---|---|---|---|
| `POST /api/v1/auth/login` | 5 / IP, 10 / email | 1 min | Brute force protection without locking out legitimate retries. |
| `POST /api/v1/auth/forgot-password` | 3 / email | 5 min | Prevents abuse of the reset mailer. |
| `POST /api/v1/auth/reset-password` | 5 / IP | 5 min | Token guessing defense (tokens are 64-char signed; this is belt + suspenders). |
| `POST /api/v1/auth/token` (bearer issue) | 10 / user | 1 min | Stops token-flood by a compromised credential. |
| All other API routes | 60 / user | 1 min | Default Laravel throttle. |

### 5.4 Account lockout
- After 10 failed login attempts in 15 minutes for a single email, the account is **soft-locked** for 30 minutes. Lock is enforced server-side regardless of IP rotation. A logged-in Organization Admin can unlock a member from the user-management screen.
- Locks write `account_locked` to `audit_logs`. Successful login after unlock writes `account_unlocked`.

### 5.5 Password policy
- Minimum 12 characters.
- Must include 3 of: lowercase, uppercase, digit, symbol.
- Cannot be one of the most common 10,000 passwords (Laravel's `Password::uncompromised()` rule using the Have I Been Pwned k-anonymity API; falls back to the local common-password list if the HIBP API is unreachable).
- Cannot be the user's email local part.
- Cannot match the last 3 passwords (hashed history kept in `password_history` table — Phase 2, not MVP).
- Rotated on first login if the password was set by an Organization Admin (forced reset flag).

### 5.6 Two-factor (Phase 2)
- TOTP via Laravel Fortify's `TwoFactorAuthenticatable` trait. Reserved columns will be added in a Phase-2 migration so MVP code does not assume their absence.

### 5.7 Impersonation (Super Admin only)
- Super Admin can impersonate any user for support.
- Implementation: short-lived bearer token with `impersonated_by` claim in the token's name, returned by `/api/v1/admin/impersonate/{user}`.
- Every action under impersonation writes both `acting_user_id` and `impersonated_by_user_id` into `audit_logs.context`.

---

## 6. Multi-tenant authentication

This is the part that diverges most from a textbook Laravel + Sanctum setup.

### 6.1 The membership model

Phase 1 implied one-user-to-one-organization. Phase 4 supersedes that. A user may belong to multiple organizations, with **different roles in each**. The truth source is the join table `organization_user`:

```
organization_user
─────────────────
id, organization_id, user_id, status, joined_at, invited_by, default_role_id
```

`users.organization_id` remains as a *convenience pointer* to the user's **default organization** — the one the SPA selects automatically on login when the user has multiple memberships and no `X-Organization-Id` was supplied. It is **not** the source of truth.

### 6.2 Organization context resolution per request

Resolution order at every request:
1. **Bearer token** carrying `abilities[]` includes an `org:<id>` ability → that org wins. (Used by integrations.)
2. **`X-Organization-Id` request header** → if the user is a member of that org, it wins. (Used by the SPA org-switcher.)
3. **Session attribute `current_organization_id`** set by an explicit `POST /api/v1/auth/select-organization` → if still a valid membership, it wins.
4. **`users.organization_id`** (the default) → fallback.
5. **None** → super-admin only; tenant-scoped models return empty unless a scope is bypassed.

The `EnsureOrganizationContext` middleware performs this resolution and:
- Calls `OrganizationContext::set($organization)` so the `OrganizationScope` global scope applies.
- Rejects the request with `409 Conflict` if the user is **not** a member of the resolved organization. This is the cross-tenant access prevention.

### 6.3 Cross-tenant isolation

Three layers, each independent:

1. **`organization_id` column on every tenant-bound row.** The schema makes cross-tenant joins physically impossible without explicit `withoutGlobalScope`.
2. **Global `OrganizationScope`** on every Eloquent model with the `BelongsToOrganization` trait. Reads and writes auto-scope.
3. **Membership check** in `EnsureOrganizationContext`. Even if an attacker tampered with `X-Organization-Id`, they get 409 because they are not in `organization_user`.

Defense in depth: every layer assumes the others might fail.

### 6.4 What about super admins?

Super admins (system-level role, see `docs/authorization.md`) are not members of any organization in `organization_user`. They bypass the membership check inside the middleware. They must supply `X-Organization-Id` explicitly to operate inside a tenant; otherwise no tenant context is set and tenant-scoped queries return nothing (safe by default).

Every super-admin action carrying `X-Organization-Id` writes that org id into `audit_logs.context` so the affected tenant can see who touched what.

---

## 7. API surface

| Method | Path | Purpose | Auth |
|---|---|---|---|
| `GET` | `/sanctum/csrf-cookie` | Issue CSRF cookie | none |
| `POST` | `/api/v1/auth/login` | Email + password login (SPA) | none, rate-limited |
| `POST` | `/api/v1/auth/logout` | Invalidate session | session |
| `GET` | `/api/v1/auth/me` | Current user + memberships | session or token |
| `POST` | `/api/v1/auth/select-organization` | Switch active org | session |
| `POST` | `/api/v1/auth/forgot-password` | Send reset email | none, rate-limited |
| `POST` | `/api/v1/auth/reset-password` | Submit new password | none, signed token |
| `POST` | `/api/v1/auth/change-password` | Change while logged in | session |
| `POST` | `/api/v1/auth/token` | Issue bearer token | session (one-time, mobile pairing) |
| `GET` | `/api/v1/auth/tokens` | List my bearer tokens | session or token |
| `DELETE` | `/api/v1/auth/tokens/{id}` | Revoke one bearer token | session or token |
| `POST` | `/api/v1/invitations/accept` | Accept invitation | signed token |
| `GET` | `/api/v1/invitations/{token}` | Inspect invitation (for the UI) | signed token |

All responses follow the envelope defined in `docs/architecture.md` §5.

---

## 8. Why these decisions

- **HttpOnly cookies for the SPA** trade away the convenience of `localStorage.getItem('token')` for genuine XSS resistance. XSS in our codebase will happen; the cookie staying out of JS reach is the difference between session theft and a noisy bug.
- **Bearer tokens with abilities** for mobile and integrations gives revocation at the row level and a scope system without inventing one.
- **Separate resolution logic for organization context** keeps authentication (who are you) and authorization context (where are you working) as two distinct concerns. They share the user, but they fail independently — and the right errors come out (401 vs 409).
- **Membership table as source of truth** means a user's "primary organization" can be removed safely; we don't lose them or their other memberships, and the audit trail records the exact moment they left.
- **Rate limits per email *and* per IP** because either alone is bypassable; together they raise the bar substantially.
- **Audit every auth event** because compliance auditors will ask "who logged in when, from where" and the answer must be one query, not a log-grep.
