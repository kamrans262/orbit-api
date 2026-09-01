$ErrorActionPreference = 'Stop'

vendor\bin\pint --test
php artisan test tests\Feature\Api\Admin\V1\AdminSosCommandCenterTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminCoreOperationsTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminFoundationTest.php
php artisan test tests\Feature\Api\V1\Sos\SosTest.php
php artisan test
php artisan route:list --path=admin/v1/sos
php artisan event:list
php artisan schedule:list
