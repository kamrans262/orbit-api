# Orbit Notifications Module Manifest

## Baseline
- Post-Activity checkpoint: 122 tests / 533 assertions
- Pint: PASS (351 files)

## Adds
- Notification preferences + per-Circle mute/silent preferences
- Private in-app notification inbox/read state
- Metadata-safe routing and idempotency
- Per-device provider-neutral push delivery records
- APNS/FCM priority/collapse metadata shaping
- Moment, Ping, encrypted-message event adapters
- SOS notification-outbox import (event-driven + every-minute recovery)
- Private `orbit.user.{userId}` realtime channel
- 90-day notification retention cleanup
- Feature tests and setup scripts

## Provider-dependent after this module
Actual APNS/FCM network delivery credentials/transports remain deployment/provider integration work. The database delivery boundary is ready for those adapters.
