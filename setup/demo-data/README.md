# Orbit Admin Console Demo Data

This development-only pack populates the Orbit Admin Console with representative data across the backend.

## Safety

The seeder and credential command refuse to run unless `APP_ENV` is `local` or `testing`.
Never copy the demo password or TOTP secret to production.

## Seed

```powershell
php artisan orbit:demo:seed
```

The command is idempotent. Re-running it refreshes deterministic demo records instead of multiplying them.

## Admin credentials

```powershell
php artisan orbit:demo:credentials
```

Or show one role:

```powershell
php artisan orbit:demo:credentials finance-manager
```

Shared development password:

```text
OrbitDemo!2026
```

The credentials command prints the current six-digit MFA code. All active role personas use the same local-only TOTP secret so the UI can be tested without configuring fourteen authenticator entries.

## Active role accounts

- super-administrator@admin.demo.orbit.test
- platform-administrator@admin.demo.orbit.test
- safety-operator@admin.demo.orbit.test
- senior-safety-operator@admin.demo.orbit.test
- moderator@admin.demo.orbit.test
- support-agent@admin.demo.orbit.test
- finance-manager@admin.demo.orbit.test
- marketing-manager@admin.demo.orbit.test
- advertising-manager@admin.demo.orbit.test
- analyst@admin.demo.orbit.test
- security-administrator@admin.demo.orbit.test
- devops-operator@admin.demo.orbit.test
- compliance-officer@admin.demo.orbit.test
- read-only@admin.demo.orbit.test

Additional state examples are also seeded for invited, disabled, locked and temporary-access administrators.

## Consumer personas

The demo dataset includes 30 consumer accounts covering free/Lite/Plus, complimentary Plus, new signup, dormant, Ghost Mode, temporary and indefinite suspension, reverification, feature restriction, high risk, pending deletion/export/privacy request, support cases, moderation reports/appeal, suspicious device, multi-device, and other everyday active-user states.

It also seeds Circles, memberships/privacy modes, Presence, Identity sessions, Pings, encrypted-message metadata, Activity, SOS incidents, moderation/risk, support/privacy, plans/subscriptions/payments/refund, advertising, communications/content/legal/regional configuration, feature flags, remote config, incidents, alerts, webhook metadata, API telemetry, WebSocket metrics, notifications and provider health.
