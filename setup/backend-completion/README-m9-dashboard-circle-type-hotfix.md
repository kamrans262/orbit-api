# Orbit Milestone 9 dashboard Circle-type hotfix

This is a test-fixture-only hotfix.

## Cause
`DashboardCompletionTest::m9Circle()` created a Circle with:

`type => persistent`

The production `App\Modules\Circles\Enums\CircleType` only accepts:

- `standard`
- `temporary`

The invalid fixture therefore threw `ValueError` before the M9 dashboard or
member-recent code was exercised.

## Fix
Replace the fixture Circle type with the valid production enum value:

`type => standard`

No production controller, service, route, migration, RBAC, privacy, SOS,
Presence, Activity, or security behavior is changed.
