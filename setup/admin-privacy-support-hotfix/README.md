# Orbit Admin Privacy/Support M5 hotfix

This focused hotfix addresses the five remaining Milestone 5 test failures.

## Production fixes
- Parse validated boolean query filters using `FILTER_VALIDATE_BOOL` instead of
  strict `=== true`, so query values such as `?overdue=1`, `?unassigned=1`,
  `?sla_breached=1`, `?expired=1`, and `?due=1` are actually enforced.

## Test-contract fixes
- Expect HTTP 428 + `ADMIN_REAUTH_REQUIRED` for stale administrator
  reauthentication, matching the Milestone 1 security contract.
- Explicitly provide a UUID when creating `DataExportRequest` inside
  `withoutEvents()`, because disabling model events also disables its UUID
  creation hook.

No migration, route, RBAC, Identity workflow, support schema, or privacy schema
changes are included.
