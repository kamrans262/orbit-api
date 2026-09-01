# Orbit SOS Backend Module

This overlay is designed for the current Orbit Laravel API checkpoint after Moments (95 tests / 368 assertions reported green by the project owner).

## Backend scope included

- Client-generated SOS UUID for idempotent activation retries.
- Circle membership authorization and archived-circle rejection.
- Maximum 3 SOS activations per 60 minutes with a structured assistance-confirmation signal.
- Persistent SOS state for restart/recovery.
- Pending responder roster created from the SOS Circle.
- Responder `engaged` / `declined` state and idempotent response handling.
- Originator and engaged-responder live location updates.
- Privacy-restricted private realtime channels:
  - `private-orbit.circle.{circleId}` for activation / resolution.
  - `private-orbit.sos.{sosId}` only for the originator and engaged responders.
- Server-authoritative escalation checks every minute:
  - T+60: re-notify unengaged responders.
  - T+180: persist an SMS-fallback escalation as `pending_provider`.
  - T+300: tell the client to show the local emergency-services number; no auto-dial.
- Highest-priority provider-neutral notification outbox for the future Notifications module.
- Opaque encrypted SOS recording reference with default 90-day retention.
- Feature tests covering authorization, idempotency, abuse limits, responders, location, resolution, escalation, and retention.

## Intentionally provider/client dependent

The Laravel module does **not** pretend these integrations are complete:

- The 3-second hold-to-arm interaction is mobile UI behavior.
- AAC recording, 1 Hz location sampling, 2-second encrypted upload chunks, offline SQLite queue, BGTask / WorkManager retries, and exponential backoff are mobile-client responsibilities.
- APNS/FCM high-priority delivery needs the Notifications module/provider credentials.
- Stage-2 SMS delivery needs subscription-tier enforcement, designated contacts, an SMS provider, and jurisdiction/compliance configuration. The backend records `pending_provider`; it does not claim a text was sent.
- Emergency-services auto-dial is deliberately absent from Orbit v1.
- The `recording_ref` must point to ciphertext managed by Orbit's encrypted media path. The SOS API never accepts audio bytes or plaintext recording content.

## Install

Merge the `orbit_api` directory into `C:\laravel-projects\orbit_api`, then from the project root run one command at a time:

```powershell
Set-ExecutionPolicy -Scope Process Bypass -Force
```

```powershell
.\setup\sos\install-sos.ps1
```

```powershell
php artisan migrate
```

```powershell
php artisan optimize:clear
```

```powershell
vendor\bin\pint
```

```powershell
vendor\bin\pint --test
```

```powershell
php artisan test tests\Feature\Api\V1\Sos\SosTest.php
```

```powershell
php artisan test
```

```powershell
php artisan route:list --path=sos
```

```powershell
php artisan event:list
```

```powershell
php artisan schedule:list
```

Do not move to the next Orbit module unless Pint, the SOS feature tests, and the complete regression suite are green.
