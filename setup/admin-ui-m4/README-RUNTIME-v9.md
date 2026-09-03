# Orbit Admin UI M4 runtime recovery v9

Purpose: repair only the six M4 moderation Blade views after a failed raw-template overlay left `__ORBIT_LAYOUT__` / `__ORBIT_SECTION__` tokens in the live project.

Safety properties:
- Package contains no live application files outside `setup/admin-ui-m4`; copying the ZIP cannot overwrite the sidebar, routes, JS, CSS, or views.
- Discovers the canonical layout and section from the already-working M3 SOS view.
- Renders all six M4 views in a staging directory first.
- Refuses installation if any placeholder remains.
- Backs up the current six moderation views before replacement.
- Installs a permanent six-route HTTP rendering smoke test.
- Automatically rolls the six views back if the smoke test fails.
- Does not modify the sidebar or M4 backend.
