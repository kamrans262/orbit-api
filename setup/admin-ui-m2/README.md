# Orbit Admin UI — Milestone 2 Core Operations overlay

This package adds production-oriented Blade/CSS/vanilla-JS UI for **Users, Devices & Sessions, and Circles** while preserving the existing protected `/api/admin/v1/...` business logic.

## Also fixes the two Milestone-1 UI issues
- Dashboard KPI cards are compacted through a scoped semantic enhancement rather than replacing the existing dashboard.
- Global/command search result containers are converted from oversized card grids into compact scrollable result rows with hover/focus treatment.

Because the exact Milestone-1 UI source was not available in the rollover attachment, these refinements are intentionally non-destructive and use semantic hooks/classes. If your M1 markup uses custom class names not matched by the enhancer, add `data-orbit-dashboard` to the dashboard root and `data-global-search-results` to the search-results container for exact targeting.

## Routes added
- `/admin/operations/users`
- `/admin/operations/users/{userId}`
- `/admin/operations/circles`
- `/admin/operations/circles/{circleId}`

The HTML shell itself contains no consumer data. All real data remains behind the existing admin API authentication/RBAC layer.

## Authentication integration
The centralized browser API client first uses an existing `window.__ORBIT_ADMIN_TOKEN__`, `window.OrbitAdmin.accessToken`, or `window.orbitAdmin.accessToken`, then common existing admin token storage keys. It never logs or renders credentials. If your Milestone-1 login uses a different token accessor, expose it as `window.OrbitAdmin.accessToken` before the M2 bundle runs.

## Mutation contract note
The previous-chat rollover preserved the route catalog and test evidence but not every M2 request class. High-risk action payloads are therefore centralized around the common `{status, reason}` / `{reason}` contracts and note/tag aliases. Read/list/detail/device/session APIs are wired to the known routes. If your current M2 request validation uses a different field name, adjust only the relevant payload in `users.js` or `circles.js`; do not duplicate backend business logic in Blade.

## Install
From the Laravel project root:

```powershell
Expand-Archive "$HOME\Downloads\orbit_api-admin-ui-core-operations-m2-overlay.zip" "$HOME\Downloads\orbit_api-admin-ui-core-operations-m2-overlay" -Force
Copy-Item "$HOME\Downloads\orbit_api-admin-ui-core-operations-m2-overlay\orbit_api\*" "C:\laravel-projects\orbit_api" -Recurse -Force
Set-ExecutionPolicy -Scope Process Bypass -Force
.\setup\admin-ui-m2\install-admin-ui-m2.ps1
```

Then run the printed verification commands one at a time. No migration is introduced by this UI package.

## Definition of done
Do not call the milestone fully verified until the installer is applied to the real project and the smoke test, existing Admin Core Operations test, production Vite build, and responsive browser checks are green.
