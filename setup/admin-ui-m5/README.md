# Orbit Admin UI Milestone 5 - Support Management

This is a setup-only package. Copying the ZIP into the Laravel project adds files only under `setup/admin-ui-m5`; the installer does not ship raw live Blade, route, CSS or JavaScript files at the ZIP root.

## Scope

M5 adds the Support Management web UI on top of the existing green privacy/support backend:

- server-filtered support ticket queue with search, status, priority, unassigned, SLA breach and pagination controls;
- support ticket detail with operational overview, conversation, internal notes, related records, attachment metadata, contact-history metadata and case timeline;
- supported backend actions are discovered from the live Laravel route inventory;
- request fields for actions are discovered from the installed controller/FormRequest source where available, so the browser does not invent a second business contract;
- internal notes remain explicitly private;
- loading, empty, error, retry and responsive states;
- accessible dialog controls and keyboard-openable queue rows.

## Previous-module protection

Before making any live change the installer:

1. runs the existing M4 rendering smoke test;
2. aborts if any live Blade installer placeholder exists;
3. validates the required support list/detail backend routes;
4. discovers the canonical M4 Blade layout and section rather than hard-coding them;
5. stages and syntax-checks all new M5 source;
6. takes a file-by-file backup of every live file it will modify;
7. hashes existing M1-M4 admin-console views/assets/routes/tests that are not supposed to change;
8. changes only `routes/web.php`, the admin-console JS entry, the single canonical Support sidebar item, and new M5 files;
9. automatically restores the checkpoint if M5 smoke/UI/backend checks fail;
10. rechecks prior UI hashes after installation.

The installer does not modify M4 moderation views/JS/CSS, M3 SOS views/JS/CSS, dashboard pages, Users, Circles, or backend domain code.

## Recommended verification

Run `verify-admin-ui-m5.ps1 -FullRegression`. It verifies M5, the existing Support backend, M4, earlier UI suites when present, Pint, the frontend production build and the full Laravel regression suite.

## Manual rollback

`rollback-admin-ui-m5.ps1` restores the latest M5 checkpoint by default. Pass `-BackupPath` only when you intentionally want a specific checkpoint.
