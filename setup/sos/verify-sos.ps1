$ErrorActionPreference = 'Stop'

vendor\bin\pint --test
php artisan test tests\Feature\Api\V1\Sos\SosTest.php
php artisan test
php artisan route:list --path=sos
php artisan event:list
php artisan schedule:list
