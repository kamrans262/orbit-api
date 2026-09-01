# Orbit Admin Core Operations — Milestone 2 Manifest

## Package identity

- Milestone: 2 — Users, Devices, Sessions & Circles Operations
- Required prior checkpoint: Milestone 1 green
- Prior full regression: 180 tests / 808 assertions
- Prior Admin Foundation: 23 tests / 111 assertions
- Prior Pint checkpoint: 526 files
- New Milestone 2 tests defined: 31
- PHP files in overlay: 57
- Total files in overlay before checksum manifest: 61

## Database

Adds migration `2026_09_02_000032_create_admin_core_operations_tables.php` with:

- `admin_user_controls`
- `admin_device_controls`
- `admin_circle_controls`
- `admin_record_notes`
- `admin_record_tags`

## Milestone 1 files intentionally replaced

Milestone 2 extends these Milestone 1 files:

- `app/Modules/Admin/Services/AdminRbacService.php`
- `app/Providers/AdminServiceProvider.php`
- `routes/admin.php`

Exact pre-Milestone-2 rollback copies are retained under:

- `setup/admin-core-operations/rollback/m1/`

## Major capabilities

- server-side user and Circle directories with pagination/filtering and pre-aggregated metrics;
- safe User/Circle operational detail;
- suspension/reactivation, feature restrictions, per-user rate limits, re-verification and risk markers;
- consumer session/device revocation and hardened access-token rotation;
- durable administrative device revocation;
- Circle freeze/archive/restore/remove controls and member enforcement;
- internal notes/tags;
- removed-Circle containment across ordinary invitations, Pings, pending message routing, Activity and notification delivery;
- active-SOS removal block and SOS preservation;
- real consumer API enforcement;
- privacy/E2EE-safe admin responses and operations;
- granular RBAC and high-risk reauthentication inherited from Milestone 1.

## Acceptance status at handoff

- PHP syntax validation: PASS — 57 PHP files / 0 failures.
- Milestone 2 feature/security tests defined: 31.
- Laravel migrations: **not executed in the generation environment**.
- Pint: **must be run in the real Orbit project**.
- Milestone 2 tests: **must be run in the real Orbit project**.
- Full regression: **must be run in the real Orbit project**.

Do not mark Milestone 2 complete until the real project gate is green.
