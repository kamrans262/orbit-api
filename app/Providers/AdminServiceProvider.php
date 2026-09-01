<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AdminCircleControl;
use App\Models\AdminUserControl;
use App\Models\Circle;
use App\Models\User;
use App\Modules\Admin\Http\Middleware\RejectAdminTokenOnConsumerApi;
use App\Modules\Admin\Operations\Http\Middleware\EnforceConsumerOperationalControls;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AdminServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        // Preserve strict identity separation in both directions: admin bearer
        // credentials are never accepted as consumer API credentials.
        $router->prependMiddlewareToGroup('api', RejectAdminTokenOnConsumerApi::class);

        // Laravel Router appends middleware to an existing middleware group
        // through pushMiddlewareToGroup(). appendMiddlewareToGroup() belongs to
        // the HTTP kernel API and is not a Router method.
        $router->pushMiddlewareToGroup('api', EnforceConsumerOperationalControls::class);

        User::resolveRelationUsing(
            'adminOperationalControl',
            fn (User $user) => $user->hasOne(AdminUserControl::class, 'user_id'),
        );

        Circle::resolveRelationUsing(
            'adminOperationalControl',
            fn (Circle $circle) => $circle->hasOne(AdminCircleControl::class, 'circle_id'),
        );

        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', 'unknown')));

            return Limit::perMinute(8)->by(hash('sha256', $email).'|'.(string) $request->ip());
        });

        RateLimiter::for(
            'admin-mfa',
            fn (Request $request): Limit => Limit::perMinute(12)->by((string) $request->ip()),
        );

        RateLimiter::for('admin-api', function (Request $request): Limit {
            $token = (string) $request->bearerToken();

            return Limit::perMinute(240)->by(
                $token !== '' ? hash('sha256', $token) : (string) $request->ip(),
            );
        });
    }
}
