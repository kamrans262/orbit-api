$ErrorActionPreference = 'Stop'
php artisan orbit:moderation:import-activity-reports
vendor\bin\pint --test
php artisan test tests\Feature\Api\Admin\V1\AdminModerationAppealsRiskTest.php
php artisan test tests\Feature\Api\V1\Activity\ActivityTest.php
php artisan test tests\Feature\Api\V1\Notifications\NotificationsTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminSosCommandCenterTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminCoreOperationsTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminFoundationTest.php
php artisan test tests\Feature\Api\V1\Sos\SosTest.php
php artisan test
php artisan route:list --path=admin/v1/reports
php artisan route:list --path=admin/v1/appeals
php artisan route:list --path=admin/v1/risk
php artisan route:list --path=v1/reports
php artisan route:list --path=v1/appeals
php artisan event:list
