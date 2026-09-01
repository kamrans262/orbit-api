$ErrorActionPreference = 'Stop'

vendor\bin\pint --test
php artisan test tests\Feature\Api\Admin\V1\AdminFoundationTest.php
php artisan test
php artisan route:list --path=admin/v1
php artisan schedule:list
