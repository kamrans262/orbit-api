# Orbit Admin Moderation, Appeals & Risk — Milestone 4

Baseline: Milestone 3 accepted green, with the previous full suite at 235 tests / 1054 assertions.

## Adds
- unified moderation report intake for user, Circle, message, Moment, Ping, SOS, and Activity targets
- reporter-submitted evidence separated from encrypted product storage
- Activity report ingestion into the unified queue, including an idempotent legacy import command
- moderation workflow: new, triaged, assigned, under_review, actioned, escalated, closed
- assignment, internal case notes, priority and risk scoring
- audited enforcement using Milestone 2 user/Circle control services
- consumer appeal submission, appeal review, restoration, and safe notification
- abuse/risk profiles and signal timelines
- privacy-safe `admin.moderation` realtime updates
- RBAC additions for Moderator, Safety, Security, Compliance, Read Only, and platform roles

## E2EE boundary
The moderation system never reads or copies message-envelope ciphertext, media encryption keys,
Moment plaintext, precise SOS location, or SOS recording references. Message/Moment/SOS target
snapshots contain metadata only. Report evidence is explicitly submitted by the reporting user.

## Gate
Migration success on MySQL is mandatory, in addition to Pint, M4 tests, Activity regression,
M3/M2/M1 regression, SOS regression, and the full suite.
