$ErrorActionPreference = 'Stop'

vendor\bin\pint --test
php artisan test tests\Feature\Api\V1\Identity\IdentityTest.php
php artisan test
php artisan route:list --path=identity
php artisan route:list --path=auth/refresh
php artisan route:list --path=me/devices
php artisan event:list
php artisan schedule:list
