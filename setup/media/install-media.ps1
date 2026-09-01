$ErrorActionPreference = "Stop"

if (-not (Test-Path ".\artisan")) {
    throw "Run this script from C:\laravel-projects\orbit_api"
}

$apiRoutes = ".\routes\api.php"
$mediaRequire = "require __DIR__.'/media.php';"

if (-not (Select-String -Path $apiRoutes -SimpleMatch $mediaRequire -Quiet)) {
    Add-Content -Path $apiRoutes -Value "`r`n$mediaRequire"
}

$consoleRoutes = ".\routes\console.php"
$scheduleLine = "\Illuminate\Support\Facades\Schedule::command('orbit:media:purge-stale')->hourly();"

if (-not (Select-String -Path $consoleRoutes -SimpleMatch "orbit:media:purge-stale" -Quiet)) {
    Add-Content -Path $consoleRoutes -Value "`r`n$scheduleLine"
}

php artisan optimize:clear
Write-Host "Orbit media routes and hourly cleanup schedule installed." -ForegroundColor Green
