$ErrorActionPreference = 'Stop'

if (-not (Test-Path ".\artisan")) {
    throw 'Run this script from C:\laravel-projects\orbit_api'
}

php artisan optimize:clear
vendor\bin\pint --test
php artisan test tests\Feature\Api\Admin\V1\AdminPrivacyComplianceSupportTest.php
php artisan test tests\Feature\Api\V1\Identity\IdentityTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminModerationAppealsRiskTest.php
php artisan test tests\Feature\Api\V1\Notifications\NotificationsTest.php
php artisan test
php artisan route:list --path=admin/v1/privacy
php artisan route:list --path=admin/v1/support
php artisan route:list --path=v1/privacy
php artisan route:list --path=v1/support
php artisan schedule:list
