$ErrorActionPreference = 'Stop'
php artisan optimize:clear
vendor\bin\pint --test
php artisan test tests\Feature\AdminUi\AdminUiM2SmokeTest.php
php artisan test tests\Feature\Api\Admin\V1\AdminCoreOperationsTest.php
npm run build
php artisan route:list --path=admin/operations
