$ErrorActionPreference = 'Stop'
php artisan route:list --path=admin/v1/communications
php artisan route:list --path=admin/v1/content
php artisan route:list --path=admin/v1/legal
php artisan route:list --path=admin/v1/regions
php artisan route:list --path=admin/v1/app-versions
php artisan route:list --path=admin/v1/maintenance
php artisan route:list --path=v1/platform
php artisan schedule:list
vendor\bin\pint --test
php artisan test tests\Feature\Api\Admin\V1\AdminCommunicationsContentRegionalTest.php
php artisan test tests\Feature\Api\V1\Notifications\NotificationsTest.php
php artisan test tests\Feature\Api\V1\Sos\SosTest.php
php artisan test
