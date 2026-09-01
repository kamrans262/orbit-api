# Orbit Notifications module

Baseline expected before install: **122 tests / 533 assertions**, Pint green.

This module provides the backend Notifications core: private in-app inbox, user and per-Circle preferences, metadata-safe notification routing, device-level push fan-out records, APNS/FCM priority shaping, realtime `notification.created`, immediate + scheduled SOS-outbox import, and retention cleanup.

## Privacy and provider boundary
- Message notifications persist only IDs plus an **encrypted preview blob**; plaintext content is not accepted into notification payloads.
- Moment notifications persist metadata only; no media ciphertext/key material is copied.
- Muted Circles remain eligible for push but deliveries are marked silent/badge-only.
- SOS ignores normal notification disables/mutes/quiet hours and remains highest priority.
- Real APNS/FCM sending is intentionally provider-dependent. `notification_deliveries` is the durable provider-neutral boundary; do not hard-code credentials into this module.

## Required regression gate
Run Pint, the Notifications feature suite, and the full Laravel suite. Any SOS/auth/privacy/security regression blocks progression.
