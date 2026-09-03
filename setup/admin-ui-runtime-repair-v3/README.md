# Orbit Admin UI M4/M5 Canonical Authentication Repair v3

This setup-only package repairs the live browser authentication path used by Moderation & Reports (M4) and Support (M5).

## Why v3 is different

Earlier runtime repair attempts collected likely token keys from broad M1-M3 source. That was still a guess and the browser continued returning HTTP 401.

v3 does not scan arbitrary Web Storage. Before changing active source it identifies the authoritative Foundation administrator-auth / M3 call graph, follows only its local imports, and derives the actual browser transport from that graph. It preserves arbitrary canonical key names instead of assuming keys contain words such as `admin` or `token`. If the canonical transport cannot be proven, installation stops before active files are changed.

M4 and M5 then share one request client generated from that proven contract. Existing Laravel administrator routes, `AuthenticateAdmin`, active-session enforcement, RBAC, reauthentication and audit middleware remain authoritative.

## Safety

- setup-only overlay: copying the package does not overwrite active application files;
- preflight runs before backup/modification;
- targeted files are backed up under `storage/app/orbit-admin-ui-runtime-repair-v3-backups`;
- a failed targeted gate automatically restores that checkpoint;
- no migrations or direct database writes;
- no sidebar/layout/theme rewrites;
- Windows Node/npm execution uses `cmd.exe /d /s /c` to avoid the Node 24 `spawnSync npm.cmd EINVAL` failure.

## Acceptance

Automated verification covers syntax, route contracts, canonical auth-source drift, M1-M5 UI regressions, Foundation security, M4/M5 backend suites, production Vite build, and optionally the complete Laravel regression suite.

The final browser gate is intentionally not claimed by the installer: after a successful full verifier, hard-refresh the browser after Foundation MFA sign-in and confirm the real Moderation and Support queues load without HTTP 401.
