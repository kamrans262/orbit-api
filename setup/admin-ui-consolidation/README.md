# Orbit Admin UI — M1 + M2 Canonical Consolidation v8

This overlay keeps the polished Admin Foundation authentication flow and the consolidated M1 + M2 operations console, while removing the duplicate login/MFA implementation that had been embedded inside `/admin`.

## Authentication architecture fixed in v4

There is now one administrator sign-in owner:

1. `/admin/login` — existing Admin Foundation credentials screen.
2. `/admin/mfa` — existing Admin Foundation MFA/TOTP screen.
3. `/admin` and `/admin/operations/*` — canonical authenticated console only.

The consolidated console no longer contains a login `<dialog>`, no longer posts credentials to `/api/admin/v1/auth/login`, and no longer implements its own MFA challenge parser. On console load it stays hidden behind a small session-verification gate and asks the protected backend `/api/admin/v1/auth/me` to validate the existing Foundation administrator session. Missing or expired authentication returns to `/admin/login`; transient network/server failures remain on the verification gate with a retry action instead of causing a login loop.

The API client prefers the existing Foundation bearer credential when it can read one, while still allowing same-origin cookie authentication if Foundation is later moved to an HttpOnly/session-cookie design. Authentication and authorization remain server-authoritative.


## Dashboard response contract fixed in v5

The completed administrator dashboard backend returns its aggregates under `data.snapshot`, with domain namespaces such as `business`, `operations`, and `environment`. v4 was reading the successful response one level too high and therefore rendered `—` even though the demo database and API were healthy.

v5 binds the UI to the completed backend contract instead of inventing demo values:

- canonical root: `data.snapshot`;
- required anchor: `data.snapshot.business.users.total`;
- business/operations/environment namespaces remain intact;
- a bounded suffix resolver supports historical placement of the same named aggregate without flattening or mutating server data;
- if the required dashboard contract disappears, the UI shows an explicit contract error instead of silently presenting an all-dash dashboard;
- the verifier runs a Node response-contract adapter test before Vite/Laravel UI gates.

No database data, seeding, dashboard counts, or API controllers are changed by this fix.

## Foundation session compatibility

The installer inspects the existing Admin Foundation JavaScript/admin Blade sources **before replacing the canonical console files** and records recognizable browser-storage **key names only**. It does not read browser storage, token values, passwords, MFA codes, or credentials. Those key names are written to `resources/js/admin-console/foundation-auth-keys.js` so the console can consume the session produced by the already-installed Foundation login.

Compatibility is intentionally bounded:

- known administrator token/session keys are supported;
- installer-detected Foundation key names are supported;
- nested administrator session objects are supported;
- same-origin cookie authentication is allowed by the API client;
- consumer-token storage is not treated as administrator authentication;
- there is no `window.fetch` or `XMLHttpRequest` interception;
- there is no second credential form or second MFA state machine.

## Canonical UI installed

- one Blade shell: `resources/views/admin/layouts/app.blade.php`
- one Admin Console stylesheet: `resources/css/admin-console.css`
- one Admin Console JavaScript runtime: `resources/js/admin-console/`
- compact responsive dashboard KPI cards backed by real server values
- compact global search/command surface with debounce, keyboard navigation and server-filtered results
- Users directory and user detail
- Devices and Sessions operations inside user detail
- Circles directory, detail and member operations
- dark/light/system appearance support
- responsive sidebar/mobile layouts
- visible focus and reduced-motion handling
- canonical web routes in `routes/admin_console.php`
- architecture/regression coverage in `tests/Feature/AdminUi/AdminConsoleConsolidationTest.php`

## Legacy M2 architecture removed after backup

The installer backs up and removes the obsolete parallel M2 layer:

- `resources/views/admin/operations/layouts/shell.blade.php`
- `resources/css/admin-ui-m2.css`
- `resources/js/admin-ui-m2/`
- `routes/admin_ui_m2.php`
- `app/Http/Middleware/InjectAdminUiM2FoundationBridge.php`
- `public/orbit-admin-m2-foundation-bridge.js`
- `tests/Feature/AdminUi/AdminUiM2SmokeTest.php`

It also removes old M2 references from `routes/web.php`, `bootstrap/app.php`, `resources/css/app.css`, and `resources/js/app.js` while preserving the established Foundation `/admin/login` and `/admin/mfa` files/routes.

## Data and security behavior

- No migration or database write is performed by this installer.
- RBAC, recent reauthentication, validation, account state and audit logging remain backend-authoritative.
- The console does not expose push tokens, private keys, ciphertext, or plaintext encrypted content.
- Sensitive identifiers remain masked in the UI where the existing server contract provides safe metadata.
- HTTP 401 returns to the canonical Foundation sign-in flow.
- HTTP 403 remains a permission failure.
- HTTP 428 remains a recent-reauthentication requirement; the UI does not bypass it.
- Server/network errors do not silently masquerade as authentication failures.

## Dashboard and global-search compatibility

The runtime keeps the existing narrow 404-only endpoint compatibility behavior.

Dashboard tries:

1. `/api/admin/v1/dashboard`
2. `/api/admin/v1/dashboard/summary`
3. `/api/admin/v1/dashboard/overview`

Global search tries:

1. `/api/admin/v1/search?q=...`
2. `/api/admin/v1/global-search?q=...`
3. `/api/admin/v1/search?query=...`

Only HTTP 404 advances to the next candidate. Authentication, authorization, validation, rate-limit, server and network failures are surfaced rather than hidden.

## Installation

Run from `C:\laravel-projects\orbit_api` after copying this overlay into the project:

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force
.\setup\admin-ui-consolidation\install-admin-ui-consolidation.ps1
```

The installer creates a timestamped filesystem backup before destructive changes and prints its path. It does not alter database data.

## Verification

Targeted gate:

```powershell
.\setup\admin-ui-consolidation\verify-admin-ui-consolidation.ps1
```

Release regression gate:

```powershell
.\setup\admin-ui-consolidation\verify-admin-ui-consolidation.ps1 -FullRegression
```

The targeted verifier checks the single-auth architecture, canonical/legacy filesystem state, Laravel cache clear, the project’s canonical no-path `vendor/bin/pint --test` discovery, the dashboard response-contract adapter, Vite production build, Admin Console UI tests, historical Foundation UI tests, Admin Core Operations, Admin Foundation security, the M9 dashboard/search backend suite when present, and the admin operation route inventory. It deliberately does not pass broad source directories to Pint because doing so changes project discovery semantics and can lint files intentionally excluded by the existing project configuration.

## Browser acceptance flow

After the automated gate is green, verify this flow in a fresh/incognito browser session:

1. Open `/admin` while signed out. It should redirect to `/admin/login`; there must be no dashboard login modal.
2. Submit the administrator email/password on the existing full-page login screen.
3. Complete the existing Foundation MFA/TOTP screen.
4. The authenticated browser should enter `/admin` and reveal the canonical console.
5. Open Users and Circles from the sidebar and verify API-backed data loads.
6. Sign out from the profile menu and verify the browser returns to `/admin/login`.

## Rollback

Use the latest installer backup:

```powershell
.\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1
```

Or use the exact path printed by the installer:

```powershell
.\setup\admin-ui-consolidation\rollback-admin-ui-consolidation.ps1 -BackupPath 'C:\path\printed\by\installer'
```

Rollback restores the pre-install filesystem checkpoint and does not touch database data.

## Verification status of this ZIP

Package-generation checks are run before delivery: JavaScript syntax, PHP syntax, CSS delimiter structure, duplicate-auth/legacy-runtime scans, installer/verifier structural checks, manifest generation and ZIP integrity. The complete Laravel checkout is not mounted in the artifact-generation container, so Pint, Vite against the real project, Laravel feature/regression tests and browser acceptance must still be run in `C:\laravel-projects\orbit_api` before this is called complete or release-ready.

## Version history

- **v2:** hardened deterministic removal of the obsolete M2 bridge and added structural postconditions.
- **v3:** aligned the two stale historical M1 dashboard presentation assertions found by full regression.
- **v4:** removes the duplicate `/admin` login/MFA dialog and delegates authentication exclusively to the established Admin Foundation `/admin/login` + `/admin/mfa` flow, with a fail-closed session verification gate and bounded Foundation-session compatibility.
- **v5:** pins the dashboard UI to the completed `data.snapshot` backend response contract, adds namespaced/suffix aggregate resolution, fails visibly on contract drift, and adds an executable response-adapter verification gate.
- **v6:** fixes the Laravel Pint `no_extra_blank_lines` regression in the canonical Admin Console consolidation test. Runtime UI/API behavior is unchanged from v5.
- **v7:** moves generated consolidation rollback checkpoints to `storage/app/orbit-admin-ui-consolidation-backups` and migrates historical v1-v6 consolidation checkpoints out of `setup/`.
- **v8:** corrects the verifier to use the project’s normal no-path Pint discovery instead of forcing broad source directories, preventing false failures in unrelated project files while retaining the rollback isolation introduced in v7.


## v8 verifier discovery correction

- Restores Laravel Pint verification to the project’s canonical `vendor/bin/pint --test` invocation with no forced path list.
- Keeps rollback checkpoints under `storage/app/orbit-admin-ui-consolidation-backups`, so historical snapshots remain outside normal source-quality discovery.
- Does not modify or auto-format unrelated M4/M6/M7/M8 backend code that v7’s forced directory scan surfaced.
- No Admin Console runtime, backend API, database, authentication, dashboard mapping, Users, Circles, or route behavior changes from v7.
