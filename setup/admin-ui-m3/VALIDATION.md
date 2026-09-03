# Orbit Admin UI M3 Validation Record

Milestone: **M3 — Safety / SOS Command Center**
Base: **canonical M1+M2 v8**

## Package-level checks completed before delivery

The artifact-generation environment does not contain the user's full Laravel checkout, database, Composer vendor tree, Vite project state, or registered runtime routes. Accordingly, these checks validate the package itself without claiming project-local release success.

Completed successfully:

- JavaScript syntax (`node --check`) for every delivered admin-console module and the M3 static contract test.
- PHP syntax (`php -l`) for the delivered admin-console route file and both delivered Admin UI Pest tests.
- M3 SOS browser static privacy/architecture contract.
- No duplicate login/MFA implementation in delivered M3 views or JavaScript.
- Canonical v8 preflight SHA-256 values rechecked against the v8 payload for every M3-modified base file.
- SOS route-contract resolver positive fixture uses the exact current Orbit backend route shape, including `GET /api/admin/v1/sos/{sosId}/sensitive-access`; assignment/classification are emitted exactly and operational closure is proven through the backend-test semantics before being bound to classification.
- SOS route-contract resolver regression fixture proves the word `Controller` can never synthesize a generic `/controls` capability.
- SOS route-contract resolver negative fixture rejects missing classification/closure capability before UI installation.
- Local JavaScript imports resolve.
- Orbit CSS custom-property references used by the delivered console are defined; stylesheet braces are balanced.
- Installer/verifier/rollback structural sanity checks.
- Package SHA-256 manifest regenerated from the final files.
- ZIP integrity test after packaging.

## Project-local release gates

The following are intentionally executed by `verify-admin-ui-m3.ps1` in the real Orbit checkout and must be green before M3 is marked complete:

1. Route-resolver self-test, then live Admin SOS route inventory -> exact generated browser contract with proven operational-closure field names.
2. `php artisan optimize:clear`.
3. Canonical project `vendor\\bin\\pint --test` discovery.
4. Existing M1 dashboard contract check when present.
5. M3 browser privacy/architecture contract.
6. Production `npm run build`.
7. Canonical M1-M3 console architecture test.
8. M3 SOS UI feature test.
9. Admin Foundation UI regression.
10. Admin SOS backend regression.
11. Admin Core Operations regression.
12. Admin Foundation security regression.
13. Consumer SOS regression.
14. Dashboard/global-search backend regression when present.
15. Canonical SOS web and Admin API route inventories.
16. Full Laravel suite when `-FullRegression` is supplied.

The installer performs **no migrations and no application database writes**. It creates only a filesystem rollback checkpoint under `storage/app/orbit-admin-ui-m3-backups` before replacing UI files.


## v3 corrective validation
- `ui.js` cancellation controls are `type="button"` and explicitly close with `cancel`, bypassing native required-field validation only for cancellation, never for confirmation.
- `show.blade.php` contains the reason-coded sensitive-access wording required by the M3 UI test.
- `tests/sos-ui-contract.mjs` fails if cancellation reverts to submit semantics.
