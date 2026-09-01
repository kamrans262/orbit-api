# Orbit Admin Safety / SOS Command Center — Milestone 3

Starting checkpoint required:

- Pint: green
- Admin Foundation: 23 tests / 111 assertions
- Admin Core Operations: 31 tests / 143 assertions
- SOS: 12 tests / 83 assertions
- Full regression: 211 tests / 951 assertions

## Scope implemented

- Dedicated `/api/admin/v1/sos` command-center endpoints.
- Active/history incident search with server-side pagination and operational filters.
- Safe incident detail with elapsed time, responder timeline, escalation timeline, push/provider routing state, retry counts, location-update health and encrypted-recording health.
- Incident assignment to administrators that actually hold `sos.manage`.
- Internal notes, internal escalation, false-alarm, technical-failure and abuse classification.
- Operational closure that does **not** silently resolve the consumer SOS event.
- Audited privacy-preserving JSON incident exports.
- Separately permissioned precise SOS location access.
- Separately permissioned opaque encrypted recording-reference access.
- Mandatory recent reauthentication + purpose + reason for sensitive access.
- Immutable dedicated sensitive-access records plus the existing immutable admin audit log.
- Admin-only `admin.safety` realtime channel and safe `admin.sos.updated` broadcast.
- Consumer SOS lifecycle events bridge into the admin realtime stream without raw location or recording references.
- Hourly purge of temporary admin SOS exports.

## Hard privacy/safety boundaries

Normal command-center endpoints never expose:
- precise latitude/longitude;
- the SOS recording reference;
- recording plaintext;
- media decryption keys;
- message/Moment plaintext or cryptographic keys.

The recording-sensitive endpoint can reveal only the existing opaque encrypted recording reference. It explicitly never exposes plaintext or decryption keys.

`Super Administrator` does **not** automatically receive `sos.location.access` or `sos.recordings.access`. `Senior Safety Operator` is the default role with sensitive safety access. Permissions remain independently assignable.

This module does not alter SOS activation/escalation/resolution rules and does not create emergency-services auto-dial behavior.

## Known telemetry boundary

The current consumer SOS backend does not collect client network telemetry or a durable country/region field. The command center therefore returns an explicit `network_health.status = unknown` rather than fabricating a signal. Location-update freshness, notification pipeline state, retries and encrypted-recording attachment state are real server-observed data. Country filtering is intentionally deferred until a trustworthy country/region source or approved geocoding integration exists; this module does not infer or fabricate it from coordinates.

## Completion gate

Do not move to Milestone 4 unless:
1. Pint is green.
2. Admin SOS Command Center tests are green.
3. Admin Core Operations tests are green.
4. Admin Foundation tests are green.
5. SOS release-blocker suite is green.
6. Full Laravel regression is green.
