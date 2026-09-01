# Orbit SOS responder idempotency hotfix

Fixes duplicate `SosResponderEngaged` realtime events when the same responder retries the same `engaged` response.

The existing SOS feature test remains unchanged and is expected to pass after applying this overlay.

Apply this ZIP over the Laravel project root, then run:

```powershell
vendor\bin\pint
vendor\bin\pint --test
php artisan test tests\Feature\Api\V1\Sos\SosTest.php
php artisan test
```

Expected behavior:

- first `engaged` response persists responder state and dispatches `SosResponderEngaged` once;
- identical retry returns the same state without another engagement event;
- a real transition from another state into `engaged` still dispatches the event.
