# Orbit Identity Hardening

This overlay completes the server-side Identity core system without replacing the already-green Email OTP, Profile, Devices, Sanctum, or encryption modules.

## Added contracts

- 15-minute Sanctum access tokens for hardened sessions.
- 60-day device-bound refresh tokens.
- Rotation on every refresh.
- Refresh-token family replay detection and full session-family revocation.
- Trusted-device approval for additional hardened device sessions.
- Profile-compatible `GET /api/v1/me/devices` plus device rename.
- Secure session list / revoke / revoke-others / logout.
- Security audit log API and cross-domain audit hooks.
- User privacy visibility summary.
- User data export request/read flow.
- Account deletion request with a 30-day reversible grace period.
- Deletion finalization blocks while the user owns a Circle.
- Final deletion pseudonymizes identity data and revokes sessions/push delivery while preserving referential integrity.
- Daily deletion finalizer and one-year primary audit retention cleanup.

## Compatibility

The existing OTP endpoint currently returns a normal Sanctum token. It remains untouched.

After OTP + device registration, clients can call:

`POST /api/v1/identity/sessions`

with the registered `device_id` to upgrade into the hardened device-bound session pair.

The public refresh endpoint is:

`POST /api/v1/auth/refresh`

This additive migration avoids replacing the working authentication module in one risky step.

## Deployment-dependent items

The product specification calls for audit replication to a separate datastore and revocation propagation across all gateway nodes within 10 seconds. This Laravel module writes the authoritative audit/session state and revokes Sanctum tokens immediately in the primary application database. Multi-region/event-bus replication remains an infrastructure deployment concern.

Clients must still wipe their encrypted MMKV/SQLite stores and local keys on sign-out/account deletion; the server cannot erase device-local encrypted storage remotely.

## Required gate

Run the Identity tests, Pint, and the entire Laravel regression suite. Do not mark the nine-core-system backend milestone complete until all are green.
