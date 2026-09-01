# Orbit Identity auth-test + device-name hotfix

This hotfix fixes the six Identity feature-test failures seen after the initial Identity hardening overlay.

Changes:
- resets Laravel's cached Sanctum RequestGuard between feature-test requests that switch bearer users;
- uses the same reset for the hardened access-token logout request;
- adds the missing nullable `devices.device_name` column used by the Identity device rename endpoint.

It does not loosen session/data-export/privacy ownership queries and does not change the Identity security contract.

After merging, run:

```powershell
php artisan migrate
php artisan optimize:clear
vendor\bin\pint
vendor\bin\pint --test
php artisan test tests\Feature\Api\V1\Identity\IdentityTest.php
php artisan test
```
