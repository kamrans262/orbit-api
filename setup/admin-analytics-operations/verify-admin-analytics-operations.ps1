$ErrorActionPreference = 'Stop'

php artisan orbit:operations:sync-integrations
vendor\bin\pint --test
php artisan test tests\Feature\Api\Admin\V1\AdminAnalyticsConfigurationOperationsTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminCommunicationsContentRegionalTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminBillingPaymentsAdvertisingTest.php
php artisan test tests\Feature\Api\V1\Notifications\NotificationsTest.php
php artisan test tests\Feature\Api\V1\Sos\SosTest.php
php artisan test
php artisan route:list --path=admin/v1/analytics
php artisan route:list --path=admin/v1/feature-flags
php artisan route:list --path=admin/v1/remote-config
php artisan route:list --path=admin/v1/system
php artisan route:list --path=v1/platform/runtime
php artisan schedule:list
php artisan event:list
