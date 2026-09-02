$ErrorActionPreference = 'Stop'

vendor\bin\pint --test
php artisan test tests\Feature\Api\Admin\V1\AdminBackendCompletionSecurityAuditTest.php
php artisan test tests\Feature\Api\V1\Dashboard\DashboardCompletionTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminAnalyticsConfigurationOperationsTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminCommunicationsContentRegionalTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminBillingPaymentsAdvertisingTest.php
php artisan test tests\Feature\Api\V1\Notifications\NotificationsTest.php
php artisan test tests\Feature\Api\V1\Sos\SosTest.php
php artisan test
php artisan route:list --path=admin/v1/dashboard
php artisan route:list --path=admin/v1/search
php artisan route:list --path=admin/v1/views
php artisan route:list --path=admin/v1/release
php artisan route:list --path=v1/dashboard
php artisan route:list --path=v1/users
php artisan orbit:release:audit
