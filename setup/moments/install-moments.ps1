$ErrorActionPreference = "Stop"

if (-not (Test-Path ".\artisan")) {
    throw "Run this script from C:\laravel-projects\orbit_api"
}

$apiRoutes = ".\routes\api.php"
$momentsRequire = "require __DIR__.'/moments.php';"

if (-not (Select-String -Path $apiRoutes -SimpleMatch $momentsRequire -Quiet)) {
    Add-Content -Path $apiRoutes -Value "`r`n$momentsRequire"
}

$consoleRoutes = ".\routes\console.php"
$scheduleLine = "\Illuminate\Support\Facades\Schedule::command('orbit:moments:purge-expired')->hourly();"

if (-not (Select-String -Path $consoleRoutes -SimpleMatch "orbit:moments:purge-expired" -Quiet)) {
    Add-Content -Path $consoleRoutes -Value "`r`n$scheduleLine"
}

php artisan optimize:clear
Write-Host "Orbit Moments routes and hourly cleanup schedule installed." -ForegroundColor Green
