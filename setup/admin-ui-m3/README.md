# Orbit Admin UI Milestone 3 — Safety / SOS Command Center

This overlay extends the **canonical M1+M2 v8 Blade/CSS/JavaScript console**. It deliberately does not create a second layout, sidebar, login page, token bridge, or frontend framework.

## Delivered UI

- Real **Safety / SOS** sidebar link in the same canonical shell.
- Active incident command center and retained incident-history view.
- Debounced server-side search/filtering and pagination.
- 10-second active-list and 8-second detail background refresh while the tab is visible; the UI labels this truthfully as auto-refresh and never claims a websocket connection is active when none is configured.
- Incident detail with escalation stage/timeline, responder acknowledgement state, provider-safe delivery metadata, network/location-update/recording-upload health metadata, assignment and internal classifications.
- Real backend mutations for Safety Operator assignment, false-alarm / technical-failure / abuse / internal-escalation classification, internal notes and operational closure. Route middleware permission metadata is discovered during installation and used to disable known unauthorized actions while backend RBAC remains authoritative.
- Authorized privacy-preserving export.
- Separate precise-location and encrypted-recording-reference reveal workflows with purpose/reason capture, backend reauthentication when required, immutable backend auditing, and a 60-second browser reveal TTL.
- Sensitive access history.
- Global search deep-links SOS/incident results into the canonical command center.
- Loading, empty, error, retry, responsive, keyboard-focus, dark/light and reduced-motion states.

## Security boundaries

The browser never reads Orbit database tables directly and the installer adds no migrations. Normal incident views whitelist privacy-safe metadata and do not render coordinates or recording references. Sensitive reveals call only the existing Admin SOS backend endpoints. Reauthentication uses the existing Foundation endpoint `/api/admin/v1/auth/reauthenticate`; no second login flow is introduced.

The installer runs a deterministic route-resolver self-test and then `php artisan route:list --json --path=admin/v1/sos` **before changing UI files**. It generates `resources/js/admin-console/sos-contract.js` from the actual registered backend route inventory. The resolver recognizes the backend's `GET .../{sosId}/sensitive-access` endpoint as sensitive-access history, and it never treats the generic `Controller` class suffix as evidence for a phantom `/controls` route. Operational closure is bound to the classification (or a true controls) mutation only when `AdminSosCommandCenterTest.php` or the routed controller/request source proves `operational_status`, resolution, and audit-reason semantics. If that proof or any required M3 capability is absent, installation stops before modifying the console.

## Base compatibility

The installer validates SHA-256 hashes for the canonical M1+M2 **v8** files it modifies. This is intentional: unknown or hand-modified UI code must be reconciled explicitly rather than overwritten and allowed to recreate the duplicated-shell problem.

## Install

From the Laravel project root:

```powershell
.\setup\admin-ui-m3\install-admin-ui-m3.ps1
.\setup\admin-ui-m3\verify-admin-ui-m3.ps1
```

After the targeted gate passes:

```powershell
.\setup\admin-ui-m3\verify-admin-ui-m3.ps1 -FullRegression
```

No milestone should be marked complete until the project-local verifier and full regression are green.


## v3 corrective release
- Cancel and close controls in required-field admin dialogs are now non-submit buttons, so operators can safely dismiss Assign, Classify, Add note, Close operationally, and sensitive-action dialogs without satisfying mutation validation.
- Sensitive-access explanatory copy now explicitly states the reason-coded requirement, matching the backend privacy contract and UI regression.
- Static M3 verification now guards the cancel/close semantics so this regression cannot silently return.
- Provider/WebSocket connectivity remains deployment infrastructure; this UI package does not disable realtime or hide provider failures.
