# Orbit Admin UI M4 + M5 Runtime Integration Repair

**Package scope:** targeted repair for the canonical Orbit Web Administration console after Milestone 4 (Moderation & Reports) and Milestone 5 (Support Management).

This package repairs two browser-runtime integration failures without replacing the canonical admin shell, introducing another login flow, changing business data, or rerunning earlier milestone installers.

## Problems repaired

### Moderation route contract

The Moderation runtime can render correctly while its generated browser route contract is empty. In that state the UI fails before making the queue request and reports that the operation is not registered by the backend.

The repair rebuilds the Moderation route contract from the **live Laravel route inventory** (`php artisan route:list --json`) and refuses installation if required reports, appeals, risk, or administrator reauthentication operations cannot be proven.

### Support / Moderation authentication handoff

M4 and M5 contained feature-local browser-token discovery. That duplicated authentication logic outside the canonical Foundation/M1-M3 admin session architecture and could leave Support unauthenticated while the administrator shell itself was already signed in.

The repair replaces those private transports with one shared `admin-api-client.js`. The shared client:

- uses only statically proven canonical administrator browser-session keys discovered from the existing admin-console source;
- supports canonical bearer-token values and structured session values;
- preserves same-origin credentials and CSRF behavior;
- retries alternate proven credentials only after a `401`;
- performs a final same-origin cookie/session request after stale bearer candidates;
- preserves backend request IDs and validation errors;
- does not enumerate arbitrary browser storage keys;
- does not persist a new credential or introduce a second authentication system.

If no canonical credential contract can be proven, the installer stops **before changing any project file**.

## Files changed in the live project

Only these runtime/integration files are changed or added:

- `resources/js/admin-console/moderation-m4.js`
- `resources/js/admin-console/moderation-routes.generated.js`
- `resources/js/admin-console/support-m5.js`
- `resources/js/admin-console/support-routes.generated.js`
- `resources/js/admin-console/admin-api-client.js` (new shared transport)
- `resources/js/admin-console/admin-auth.generated.js` (generated canonical auth contract)
- `tests/Feature/AdminUi/AdminRuntimeIntegrationContractTest.php` (new architecture regression)

The repair does **not** change Blade layouts, sidebar/navigation, CSS, web routes, API controllers, migrations, database records, RBAC assignments, demo data, or the canonical Foundation login/MFA flow.

## Safety and rollback

Before changing live source, the installer creates a timestamped checkpoint under:

`storage/app/orbit-admin-ui-runtime-repair-backups/<timestamp>`

The backup contains the exact pre-repair files plus SHA-256 metadata. If any targeted install gate fails, the installer automatically restores that checkpoint and clears Laravel caches.

A manual rollback command is also included.

## Install

Run from PowerShell after copying this package into the Orbit project:

```powershell
cd C:\laravel-projects\orbit_api
Set-ExecutionPolicy -Scope Process Bypass -Force
.\setup\admin-ui-runtime-repair\install-admin-ui-runtime-repair.ps1
```

The installer performs:

1. live route-inventory preflight;
2. canonical auth-contract discovery;
3. pre-change backup;
4. deterministic M4/M5 route-contract generation;
5. shared transport integration;
6. JavaScript syntax checks;
7. PHP syntax and targeted Pint check;
8. targeted UI/backend regression tests present in the project;
9. production Vite build;
10. static runtime architecture verification.

No migration or database mutation command is executed.

## Release verification

After a successful install, run the full regression gate:

```powershell
.\setup\admin-ui-runtime-repair\verify-admin-ui-runtime-repair.ps1 -FullRegression
```

This repeats the runtime contract checks and adds the full project Pint and Laravel regression suites.

## Browser acceptance gate

Automated tests are not the final acceptance for this repair. After the full verifier is green:

1. hard-refresh the admin console (`Ctrl+F5`);
2. open **Moderation & Reports** and verify the real report queue loads;
3. open **Support** and verify the real support queue loads;
4. exercise a non-destructive filter/retry flow on both pages;
5. confirm there is no `operation is not registered` error and no unexpected `Administrator authentication is required` error.

Do not mark M4/M5 runtime integration accepted until both real browser queues load under the signed-in canonical administrator session.

## Manual rollback

Use the backup path printed by the installer:

```powershell
.\setup\admin-ui-runtime-repair\rollback-admin-ui-runtime-repair.ps1 `
  -BackupPath 'C:\laravel-projects\orbit_api\storage\app\orbit-admin-ui-runtime-repair-backups\YYYYMMDDHHMMSS'
```

The rollback restores exactly the files recorded in the backup manifest and removes repair-created files that did not exist before installation.

## Engineering notes

This is intentionally a narrow integration repair. It avoids broad rewrites and keeps domain-specific Moderation and Support UI behavior in their existing modules while centralizing only the cross-cutting administrator HTTP/authentication concern. Route contracts remain generated from Laravel rather than hard-coded, and the new regression test prevents a future package/recovery copy from silently returning to an empty route map or feature-local token reader.

## Windows PowerShell / Node command execution hardening (v2)

The installer and verifier do not spawn `npm.cmd` directly from Node on Windows. Node 24 on some Windows configurations can return `spawnSync npm.cmd EINVAL` even though `npm run build` works interactively in PowerShell. The v2 command adapter invokes npm through the system command processor (`ComSpec /d /s /c npm ...`) while continuing to use direct process execution for PHP and Node. A package self-test asserts this Windows command construction before the repair runs.
