$ErrorActionPreference = "Stop"

if (-not (Test-Path ".\artisan")) {
    throw "Run this script from C:\laravel-projects\orbit_api"
}

Write-Host "Installing Laravel Reverb..." -ForegroundColor Cyan
composer require laravel/reverb

Write-Host "Publishing Reverb configuration..." -ForegroundColor Cyan
php artisan reverb:install --no-interaction

Write-Host "Backing up bootstrap/app.php..." -ForegroundColor Cyan
Copy-Item ".\bootstrap\app.php" ".\bootstrap\app.php.pre-realtime-backup" -Force

Write-Host "Applying Orbit mobile broadcasting configuration..." -ForegroundColor Cyan
Copy-Item ".\setup\realtime\bootstrap.app.php" ".\bootstrap\app.php" -Force
Copy-Item ".\setup\realtime\channels.php" ".\routes\channels.php" -Force

php artisan optimize:clear

Write-Host ""
Write-Host "Realtime installation finished." -ForegroundColor Green
Write-Host "Next run:"
Write-Host "  vendor\bin\pint"
Write-Host "  vendor\bin\pint --test"
Write-Host "  php artisan test"
Write-Host "  php artisan event:list"
Write-Host ""
Write-Host "To start the WebSocket server:"
Write-Host "  php artisan reverb:start --debug"
