# Orbit Admin Communications / Content / Regional — Milestone 7

Baseline expected before install: **344 Laravel tests green**.

Milestone 7 provides production-oriented administrative backends for targeted communications, announcements, localization, CMS content, legal versions/acceptance, country/regional configuration, app-version policy, and maintenance windows.

## Provider boundary

- In-app campaign delivery writes real Orbit notification records.
- Push campaign delivery writes durable `notification_deliveries` rows with `pending_provider`; it does not claim APNs/FCM success.
- Email campaign delivery uses Laravel Mail through the scheduled provider-dispatch command.
- SMS is explicitly marked `provider_unconfigured` until a real SMS provider is connected. No fake SMS success is recorded.

## Safety and privilege boundaries

- Emergency communication requires `communications.emergency.send` plus recent administrator reauthentication.
- That permission is separately assignable and is not automatically granted to Super Administrator.
- Legal publication, regional changes, app-version policy changes, and maintenance activation/cancellation require sensitive domain permissions and recent reauthentication.
- Maintenance middleware never blocks admin operations, consumer authentication/Identity, Notifications, Support/Appeals, platform config, or SOS APIs.
- Every maintenance payload explicitly reports `sos_available: true`.

## Localization workflow

Template, announcement, CMS, and legal translations use draft → review → publish. Publication requires at least one reviewed/published translation. Consumer delivery uses requested locale with English fallback.

## Required gate

Run migration, RBAC sync, Pint, the M7 feature/security suite, Notifications and SOS regressions, then the full Laravel regression suite. Any auth/security/privacy/SOS regression blocks progression.
