# Orbit Identity Hardening Module Manifest

Baseline expected before merge:

- Notifications: 17 tests passed.
- Full regression: 139 tests / 601 assertions.
- Pint: green.

This overlay completes the ninth named core system: Identity.

## Security features

- Device-bound hardened session records.
- 15-minute access token expiry.
- 60-day rotating refresh-token families.
- Refresh replay detection.
- Family/session revocation.
- Trusted-device approval state.
- Session/device management.
- Security audit trail.
- Deleted-account API blocking.
- 30-day reversible deletion lifecycle.
- Circle-owner deletion safety gate.
- Server-visible data export.

## Files intentionally not replaced

- Existing Email OTP controllers/actions.
- Existing Device module controllers/actions/models.
- Existing Profile module.
- Existing Sanctum configuration.
- Existing Messaging/Media encryption code.
- Existing Circle ownership business logic.

The module integrates additively through routes, a service provider, middleware, listeners, and new tables.
