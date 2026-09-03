# Orbit Admin UI Consolidation Manifest — v8

Package scope: Admin UI Foundation M1 + Core Operations UI M2 canonical consolidation with single Foundation-owned administrator authentication, the completed M9 dashboard response contract, and rollback-safe source-quality verification using the project canonical Pint discovery.

| File | Bytes | SHA-256 |
|---|---:|---|
| `README.md` | 10783 | `a93f673025d9488954e0c569ff6774c7ea855e22b03690f6fa3c04a6f280a106` |
| `VALIDATION.md` | 3521 | `d10f7ec647186ffc165090ffd2b16034bcf61f5a307525f091aead31e28744bc` |
| `install-admin-ui-consolidation.ps1` | 25187 | `a17418136fdc484baec59554428c2ddb14e748539c77d4c0e2ab84af8ec98608` |
| `payload/resources/css/admin-console.css` | 36895 | `cb2c2e808c1e31ae730544a252a036107a987e4d2557b1958ff0c95c51d14b9a` |
| `payload/resources/js/admin-console/api-client.js` | 6394 | `0f2bebdbfebefb46ae5cce13854a49980df9507499faed8595173190fc0a4144` |
| `payload/resources/js/admin-console/auth-session.js` | 6831 | `79438badf60a04fe751294ecbbc4bf46c77d1820187da3dcdd39e0a0d1fbee8f` |
| `payload/resources/js/admin-console/circles.js` | 7272 | `841e54eef9775d64f4e297ab9035022984aac1ff1ae000df9412d520cff438cf` |
| `payload/resources/js/admin-console/dashboard.js` | 10191 | `1eb5eb5d5f381900539eac1d55df6fe15a762c2d56e9f8c48944deaaedf0a656` |
| `payload/resources/js/admin-console/foundation-auth-keys.js` | 283 | `60ac79c8f39ba4c6d8fdca1b8c4ae49c68f46afadc60c37c38d9b5165fa29f1d` |
| `payload/resources/js/admin-console/index.js` | 1340 | `b5be03287370400f30d5375b1cfa0e091c5311fc498b6e594923edcc663d887a` |
| `payload/resources/js/admin-console/shell.js` | 15062 | `29a02cda2ac607e28d92da50eae0d33c7fb5aa8f46e21251208a2cd8636000b2` |
| `payload/resources/js/admin-console/ui.js` | 4781 | `41e14b839c8dbc29247645ca7620e8e40ad8e20ed27206c023761afae19b3ee4` |
| `payload/resources/js/admin-console/users.js` | 10273 | `a60370c324909c5ec9d32facac1ec130f352b3d97203b98b3a5edab1f3ba1d7d` |
| `payload/resources/views/admin/dashboard.blade.php` | 2758 | `b505bce9ee0a45a627769b684170c7ca0de199afdf5f433e2ac725a29417728c` |
| `payload/resources/views/admin/layouts/app.blade.php` | 3337 | `b2aaf0761e6a7927f59d5f8a022caa46198279214d6fdfb56623551fb9b79bf1` |
| `payload/resources/views/admin/operations/circles/index.blade.php` | 2835 | `80f2edf37e8a7e84d228fe3d64ff2cb45e7298b92b47327cf911b8048e87cc75` |
| `payload/resources/views/admin/operations/circles/show.blade.php` | 2903 | `85d8cbd2f82048565e65ee50fb33dc2c390497b16fce3c93039e887161602852` |
| `payload/resources/views/admin/operations/users/index.blade.php` | 2836 | `26d9801eee5146cf6ecef18f47d7f88a7375abb63a80e73b44408986762bae2c` |
| `payload/resources/views/admin/operations/users/show.blade.php` | 3151 | `10c903dc5d2bd50db61841c75b1889c0abc1e7ca1bd72de52bcfa22edfaec98e` |
| `payload/resources/views/admin/partials/sidebar.blade.php` | 5186 | `096e2eef78584f0ce167a00452cfc6d9db987863d835d26d62a13bd3e64c7646` |
| `payload/resources/views/admin/partials/topbar.blade.php` | 1644 | `35bbc8f5b680fe3bd045b2c60d38bab4996d222202bddefcf2c82a7aa2925e1d` |
| `payload/routes/admin_console.php` | 785 | `743d8bdff14a37870c964ef414bbef16960525bc249306ae853920d8a14b37d1` |
| `payload/tests/Feature/AdminUi/AdminConsoleConsolidationTest.php` | 5356 | `a77a2fc78a45853b56c06b8a1840a5d8c955d2f536258f7d024e0e26b0fd777d` |
| `rollback-admin-ui-consolidation.ps1` | 3253 | `5f6c69bce6098099fe96f6af011accadc2d805336031271e18b5e2ef138db5f1` |
| `tests/dashboard-contract.mjs` | 2800 | `74596905af23085efff5645259d504eea7a72cbc4531a96083eba50649b070fb` |
| `verify-admin-ui-consolidation.ps1` | 9237 | `d718bcf31f02f767bf8edf88f24376955c3d8a91b22ea98608467f9a43a4906a` |
## v5 dashboard response-contract reconciliation

- Binds the dashboard adapter to the completed `data.snapshot` response wrapper.
- Pins the required anchor to `data.snapshot.business.users.total`, which is asserted by the existing M9 backend regression.
- Resolves aggregate paths across the preserved `business`, `operations`, and `environment` namespaces without hardcoded demo values.
- Uses an unambiguous suffix fallback only for historical placement of the same named aggregate.
- Fails visibly with `dashboard_contract_mismatch` instead of rendering a successful all-dash dashboard when the contract drifts.
- Adds `setup/admin-ui-consolidation/tests/dashboard-contract.mjs` to the targeted verification gate.
- Keeps the v4 single-authentication architecture and M1/M2 canonical shell unchanged.

## v6 Pint formatting correction

- Removes the extra blank line in `AdminConsoleConsolidationTest.php` that triggered Laravel Pint `no_extra_blank_lines`.
- The same corrected source is used for both the installer payload and the installed canonical test.
- No Admin Console runtime, backend API, database, authentication, dashboard mapping, Users, Circles, or route behavior changes from v5.

## v7 rollback-artifact isolation

- Stores new rollback checkpoints under `storage/app/orbit-admin-ui-consolidation-backups` instead of the source-controlled `setup/` tree.
- Migrates historical consolidation checkpoints created under `setup/admin-ui-consolidation/backups` into the storage backup area before creating the next checkpoint.
- Keeps `.last-backup.txt` pointing to the exact current checkpoint and keeps rollback compatible with both the new and legacy backup roots.
- Scopes the verifier Pint gate to active Laravel source roots so preserved historical snapshots can never fail a release gate.
- No Admin Console runtime, backend API, database, authentication, dashboard mapping, Users, Circles, or route behavior changes from v6.


## v8 canonical Pint discovery correction

- Uses `vendor/bin/pint --test` with no forced source directory arguments, matching the project’s established baseline/release command.
- Keeps rollback checkpoints under `storage/app`, so old snapshots do not participate in normal project discovery.
- Avoids auto-formatting or otherwise mutating unrelated backend modules surfaced only by v7’s verifier-specific directory override.
- Runtime UI/API/database/auth/dashboard behavior is unchanged from v7.
