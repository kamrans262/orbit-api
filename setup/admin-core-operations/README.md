# Orbit Admin Core Operations — Milestone 2

## Required starting checkpoint

Milestone 1 must already be green:

- Pint: 526 files green
- Admin Foundation: 23 tests / 111 assertions
- Full regression: 180 tests / 808 assertions
- 24 `/api/admin/v1/*` foundation routes

## Scope

This milestone implements the administrative operations layer for Users, Devices, Sessions, and Circles on top of the existing Orbit consumer backend.

### Users

- server-side paginated/searchable user directory;
- operational filters for account status, registration/verification, platform, risk, activity dates, deletion state, SOS history, and Circle-count ranges;
- pre-aggregated/eager-loaded directory metrics to avoid obvious per-row N+1 queries;
- safe user operational profile;
- suspension/reactivation;
- selected feature restrictions;
- per-user administrative rate limits;
- required re-verification;
- warning/risk classification and Trust & Safety escalation marker;
- force logout and hardened-session revocation;
- internal notes and tags.

### Devices and sessions

- safe device metadata only;
- push-token health without exposing token values;
- no private/public cryptographic key material returned;
- trusted-device state and active hardened-session count;
- device revocation with a durable admin enforcement lock;
- admin-revoked devices cannot silently re-register or issue new hardened sessions;
- mark suspicious / require verification;
- force hardened access-token rotation while preserving the refresh family;
- list/revoke selected hardened sessions and force logout all consumer credentials.

### Circles

- server-side paginated/searchable Circle directory;
- pre-aggregated directory metrics for members, Activity, reports, SOS and safety tags;
- owner/member/role and safe operational detail;
- activity/SOS/report counters;
- freeze, archive, restore, and remove enforcement states;
- selected Circle feature restrictions;
- enforcement removal of non-owner members;
- internal notes and tags.

### Removed-Circle containment

A platform removal is stronger than ordinary archival and is intentionally safety-aware:

- removal is **blocked while the Circle has an active SOS incident**;
- outstanding Circle invite codes are revoked;
- pending Pings are expired;
- server-held pending message routing records for the Circle are purged (client-side E2EE copies are unaffected);
- non-SOS Activity items are removed from ordinary feeds;
- ordinary in-app notifications are hidden and pending provider deliveries are cancelled;
- SOS Activity history and SOS notifications/deliveries are preserved;
- direct consumer resource URLs for Moments, Media, Pings, message envelopes/uploads and other resolvable Circle resources are checked against the operational Circle state;
- encrypted retained media is not decrypted or destructively erased by this operational action; retention/compliance remains authoritative.

## Consumer enforcement

Administrative controls are enforced on the real consumer API rather than stored as UI-only flags. The Milestone 2 middleware is appended to the existing API middleware group and preserves the Milestone 1 two-way admin/consumer token separation.

Important safety rule: per-user administrative rate limiting explicitly does **not** throttle `/api/v1/sos/*`. SOS keeps its own server-authoritative abuse/safety rules. Ordinary Circle freeze/feature restrictions also do not intercept SOS routes.

## Privacy / E2EE boundary

This milestone does not add administrative access to:

- message plaintext or ciphertext payloads;
- media or Moment decryption keys;
- private cryptographic key material;
- push token values;
- precise Presence coordinates;
- SOS recording contents.

User detail exposes only Presence operational health/metadata and a boolean indicating whether a location sample exists.

## Deferred scope

These are intentionally deferred to later milestones because their underlying administrative domains do not exist yet:

- subscription/entitlement filters and actions;
- generalized moderation/report-state filters and enforcement cases;
- support-case history;
- full country/region operations;
- administrative initiation/delivery of privacy exports/deletion cases;
- exceptional precise-location/SOS-recording access (Milestone 3 with separate permission + reauthentication + reason + immutable audit).

## Completion gate

Do not mark Milestone 2 complete until all are green in the real Orbit project:

1. migration;
2. `orbit:admin:sync-rbac`;
3. Pint;
4. Milestone 2 feature/security tests;
5. Milestone 1 regression tests;
6. SOS regression tests;
7. complete Laravel regression suite;
8. admin user/Circle routes verified.

The generated overlay is syntax-validated before handoff, but the real Laravel runtime/test suite remains the authoritative acceptance gate.
