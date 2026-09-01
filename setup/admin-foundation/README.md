# Orbit Admin Platform Foundation — Milestone 1

Baseline before installation:

- Migrations: up to date
- Pint: PASS, 448 files
- Identity: 18 tests / 96 assertions
- Full regression: 157 tests / 697 assertions

## Included

- Separate `AdminUser` identity; consumer Orbit tokens are rejected by admin middleware, and admin tokens are rejected by consumer/API broadcasting routes.
- Dedicated `/api/admin/v1/*` API namespace.
- Mandatory administrator MFA with RFC 6238 TOTP and one-time recovery codes.
- Invitation -> password -> MFA setup -> activation flow.
- Initial bootstrap invitation command that refuses routine use after administrator records exist unless `--force` is explicit.
- Short-lived administrator access tokens plus absolute and idle session expiry.
- Administrator self-session list/revoke and privileged forced session revoke.
- Recent password + MFA reauthentication for high-risk actions.
- Login attempt history, new-IP suspicion flag foundation, and account lockout after repeated failures.
- 14 default role types from the Admin Console scope.
- Granular role/permission pivot model, custom roles, and independently controlled sensitive permission records.
- No implicit super-admin permission bypass. Future high-risk permissions remain separately assignable.
- Administrator invitation/deactivation/reactivation/role assignment APIs.
- Immutable admin audit model with actor, session, request ID, target, result, reason, before/after state, and metadata sanitization.
- Request ID propagation through `X-Request-Id` and response bodies.
- Named admin API/login/MFA rate limits.
- Daily stale invitation/MFA challenge cleanup.
- 23 feature/security tests covering two-way admin/consumer token separation, MFA setup/login lockout, recovery codes, session expiry, RBAC, reauthentication, forced logout, audit immutability, validation, and request IDs.

## Deliberately not claimed in Milestone 1

- WebAuthn/passkeys: the schema and auth boundary leave room for this, but TOTP + recovery codes are the Milestone 1 production MFA mechanism. A security-reviewed WebAuthn implementation can be added without weakening TOTP.
- IP allowlists: login/IP history is recorded and hashed; allowlist enforcement can be added with the Security milestone when network policy and proxy trust configuration are finalized.
- User/Circle/SOS/moderation/billing admin functionality: later milestones only.
- Admin web UI: intentionally deferred until backend/admin APIs are complete. Before UI implementation begins, stop and request the product owner's further UI instructions.

## Install

Merge this overlay into the Orbit project root, then run:

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force
```

```powershell
.\setup\admin-foundation\install-admin-foundation.ps1
```

```powershell
php artisan migrate
```

```powershell
php artisan orbit:admin:sync-rbac
```

```powershell
php artisan optimize:clear
```

```powershell
vendor\bin\pint
```

```powershell
vendor\bin\pint --test
```

```powershell
php artisan test tests\Feature\Api\Admin\V1\AdminFoundationTest.php
```

```powershell
php artisan test
```

```powershell
php artisan route:list --path=admin/v1
```

```powershell
php artisan schedule:list
```

Do not advance to Milestone 2 unless the admin-specific suite, Pint, and the entire Orbit regression suite are green.

## Bootstrap the first administrator

Only after the Milestone 1 regression gate is green:

```powershell
php artisan orbit:admin:bootstrap your-admin-email@example.com --name="Orbit Administrator"
```

The command emails the invitation and prints the activation token once for bootstrap/recovery purposes. Normal future administrators must be invited through the protected admin API.

## Optional production configuration

The defaults are safe for development, but production may set:

- `ADMIN_CONSOLE_URL`
- `ADMIN_SESSION_LIFETIME_MINUTES` (default 480)
- `ADMIN_IDLE_TIMEOUT_MINUTES` (default 15)
- `ADMIN_REAUTH_WINDOW_MINUTES` (default 10)
- `ADMIN_MFA_CHALLENGE_MINUTES` (default 5)
- `ADMIN_MFA_SETUP_MINUTES` (default 20)
- `ADMIN_MFA_MAX_ATTEMPTS` (default 5)
- `ADMIN_INVITATION_HOURS` (default 24)
- `ADMIN_FAILED_LOGIN_LIMIT` (default 5)
- `ADMIN_LOCKOUT_MINUTES` (default 15)
- `ADMIN_RECOVERY_CODE_COUNT` (default 10)

Do not put administrator secrets in source control.
