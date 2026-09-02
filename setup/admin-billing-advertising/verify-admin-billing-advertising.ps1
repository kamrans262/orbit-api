$ErrorActionPreference='Stop'
vendor\bin\pint --test
php artisan test tests\Feature\Api\Admin\V1\AdminBillingPaymentsAdvertisingTest.php
php artisan test tests\Feature\Api\V1\Sos\SosTest.php
php artisan test
php artisan route:list --path=admin/v1/billing
php artisan route:list --path=admin/v1/subscriptions
php artisan route:list --path=admin/v1/payments
php artisan route:list --path=admin/v1/refunds
php artisan route:list --path=admin/v1/advertising
php artisan route:list --path=v1/me/subscription
php artisan route:list --path=v1/ads
php artisan schedule:list
