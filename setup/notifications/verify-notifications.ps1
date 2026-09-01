$ErrorActionPreference = 'Stop'
php artisan route:list --path=notifications
php artisan event:list
php artisan schedule:list
vendor\bin\pint --test
php artisan test tests\Feature\Api\V1\Notifications\NotificationsTest.php
php artisan test
