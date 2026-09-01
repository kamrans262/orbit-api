# Orbit Admin Core Operations M2 Router Hotfix

Replaces `app/Providers/AdminServiceProvider.php`.

## Fix
Laravel's `Illuminate\Routing\Router` appends middleware to an existing middleware
group with `pushMiddlewareToGroup()`. The Milestone 2 provider incorrectly called
`appendMiddlewareToGroup()`, which caused every Artisan command to fail during
service provider boot.

The hotfix changes only that integration call and keeps the existing middleware
ordering:

1. `RejectAdminTokenOnConsumerApi` remains prepended to the `api` group.
2. `EnforceConsumerOperationalControls` is pushed to the end of the `api` group.

After merging, resume the Milestone 2 validation sequence from `php artisan migrate`.
