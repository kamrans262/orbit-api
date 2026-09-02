# Orbit Milestone 6 route/catalog hotfix

This focused hotfix fixes the first Milestone 6 local run.

## 1. Installer route wiring
The original PowerShell installer used `Add-Content`, which failed on the local
Windows environment with `Stream was not readable` after the consumer route
include had been added. The replacement installer uses absolute `.NET`
`ReadAllText` / `WriteAllText` calls and is idempotent, so rerunning it safely
adds only missing route/console includes.

## 2. Entitlement response shape
Billing entitlement slugs such as `ads.enabled` were returned as flat dotted
keys. The consumer contract and advertising service both use nested access
(`data.entitlements.ads.enabled` / `data_get(..., 'ads.enabled')`). Entitlements
are now returned as a nested structure.

## 3. M6 test catalog setup
`RefreshDatabase` clears the Free/Lite/Plus catalog for each feature test.
The M6 suite now synchronizes the default catalog in `beforeEach()`, making
catalog-dependent tests deterministic.

No migration, schema, RBAC, payment, refund, subscription lifecycle, or SOS
logic changes are included.
