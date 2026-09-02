$ErrorActionPreference = 'Stop'

if (-not (Test-Path '.\artisan')) {
    throw 'Run this script from C:\laravel-projects\orbit_api'
}

throw @'
This legacy realtime installer is intentionally disabled.

Orbit Reverb is already integrated. Re-running the old installer would overwrite
bootstrap/app.php and routes/channels.php and could remove current API/broadcast
wiring. Configure the existing Reverb installation through environment values
(REVERB_HOST, REVERB_ALLOWED_ORIGINS, rate limits, Redis/scaling) instead.

Use `php artisan orbit:release:audit` and the System Operations endpoints to
verify production readiness.
'@
