# Package Generation Validation — v8

Checks performed in the artifact-generation environment before ZIP creation:

- JavaScript syntax: `node --check` on every file in `payload/resources/js/admin-console/`.
- Dashboard response adapter: executable Node fixture proving `data.snapshot.business.users.total` and namespaced operations/health values populate the UI view model.
- Dashboard drift behavior: missing canonical user aggregate throws a visible `dashboard_contract_mismatch` instead of rendering an all-dash success state.
- PHP syntax: `php -l` on the canonical route file and Admin Console consolidation test.
- Pint-source formatting regression check: canonical Admin Console test contains no consecutive extra blank line that triggers `no_extra_blank_lines`.

- Verifier Pint-discovery check: Pint is invoked using the project’s canonical no-path `--test` command, preserving existing project finder/configuration semantics; no broad directory list is forced.
- Rollback isolation check: generated rollback checkpoints remain under `storage/app/orbit-admin-ui-consolidation-backups`, outside the source tree that previously caused false Pint failures.
- Backup-location check: new rollback checkpoints are stored under `storage/app/orbit-admin-ui-consolidation-backups`; legacy consolidation checkpoints under `setup/admin-ui-consolidation/backups` are migrated there before installation.
- Rollback compatibility check: rollback searches both the new storage location and the legacy setup location, and `.last-backup.txt` continues to point to the exact current checkpoint.
- CSS structural check: balanced braces and parentheses in `admin-console.css`.
- Duplicate-auth scan: runtime layout contains no `data-admin-auth-dialog` / `data-admin-auth-form`.
- Auth ownership scan: canonical layout declares `data-orbit-auth-owner="foundation"` and includes the session verification gate.
- Auth-runtime scan: canonical shell contains no `/api/admin/v1/auth/login`, `/api/admin/v1/auth/mfa/verify`, or `writeAdminSession` implementation.
- Session-compatibility scan: `auth-session.js` contains no `window.fetch` or `XMLHttpRequest` interception.
- Session-reader behavior check: a nested Foundation-shaped admin session resolves, while consumer-only storage is not accepted as an administrator session.
- Legacy M2 runtime scan: no obsolete `admin-ui-m2`, `admin_ui_m2`, bridge middleware, or public bridge artifact in the runtime payload.
- Canonical navigation/runtime marker scan.
- PowerShell structural delimiter/string-state sanity scan for installer, verifier and rollback scripts.
- Manifest hashes regenerated from the final package tree.
- Final ZIP integrity test and SHA-256 digest after packaging.

Not executable in this generation environment because the user's complete Laravel checkout/runtime is not mounted:

- Laravel application boot for the installed overlay
- Laravel Pint against the full project
- Vite build against the full project and installed dependencies
- Laravel feature tests against the full application
- full Laravel regression suite
- live browser login → MFA → dashboard acceptance
- responsive browser testing against the running application/database

Run the following in the real Orbit checkout before treating v7 as complete:

```powershell
.\setup\admin-ui-consolidation\verify-admin-ui-consolidation.ps1
.\setup\admin-ui-consolidation\verify-admin-ui-consolidation.ps1 -FullRegression
```

Then perform the browser acceptance flow documented in `README.md`.
