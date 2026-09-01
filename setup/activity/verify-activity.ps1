$ErrorActionPreference = 'Stop'

php artisan route:list --path=activity
php artisan event:list
vendor\bin\pint --test
php artisan test tests\Feature\Api\V1\Activity\ActivityTest.php
php artisan test
