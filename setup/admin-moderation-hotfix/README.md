# Orbit Milestone 4 suspension / appeal hotfix

This overlay fixes the two failures from the first real M4 run and closes the production suspension-appeal access gap they exposed.

## Root causes
1. The M4 suspension test called `/api/v1/me`, but Orbit's real authenticated identity endpoint is `/api/v1/auth/me`. The 404 was a test-path error, not a suspension enforcement failure.
2. Suspended users were blocked from `POST /api/v1/appeals`. Since M2 correctly revokes all normal consumer sessions and also blocks normal OTP verification while suspended, there was no production-safe way to authenticate an appeal after suspension.

## Fix
- Corrects the M4 regression test to `/api/v1/auth/me`.
- Allows only the appeal submission endpoint through suspension/re-verification controls.
- Adds dedicated public appeal OTP endpoints under `/api/v1/appeals/auth/email-otp/{request,verify}`.
- Appeal OTP verification is available only to a currently suspended user for an active user-targeted enforcement.
- It issues a 30-minute Sanctum token with only `appeals:submit` ability.
- Appeal-only tokens are blocked from every non-appeal consumer API by the operational-control middleware.
- The appeal controller also checks `appeals:submit`, providing defense in depth.
- The public OTP request returns the same 202 response for unknown/ineligible data to prevent user/enforcement enumeration.
- Normal `/api/v1/auth/email-otp/verify` remains blocked during suspension, preserving the M2 contract.

No migration or RBAC synchronization is required.
