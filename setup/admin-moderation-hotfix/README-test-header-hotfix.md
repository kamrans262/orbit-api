# Milestone 4 appeal OTP test-header hotfix

This hotfix changes only:

`tests/Feature/Api/Admin/V1/AdminModerationAppealsRiskTest.php`

## Cause

Laravel feature-test `withHeaders()` state persists on the test client. The
`suspended users can obtain a short lived appeal only token by email OTP` test
first performed an administrator request and then called the public appeal OTP
endpoint without clearing the administrator Authorization header.

Orbit's two-way token isolation middleware correctly rejected that leaked admin
token on a consumer endpoint with HTTP 401.

The production route is already public. This is independently proven by the
following test, which was already passing:

`appeal OTP request does not enumerate users or enforcement identifiers`

## Fix

Clear the persisted Authorization header before calling the public appeal OTP
request / verify endpoints.

No production route, middleware, authentication, schema, or appeal behavior is
changed.
