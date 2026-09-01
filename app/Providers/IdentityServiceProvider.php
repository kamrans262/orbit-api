<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Identity\Http\Middleware\AuditSecurityMutation;
use App\Modules\Identity\Http\Middleware\EnsureIdentityAccountActive;
use App\Modules\Identity\Listeners\AuditSosActivated;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (class_exists('App\\Modules\\Sos\\Events\\SosActivated')) {
            Event::listen('App\\Modules\\Sos\\Events\\SosActivated', AuditSosActivated::class);
        }

        Event::listen(RouteMatched::class, function (RouteMatched $event): void {
            $route = $event->route;
            $uri = ltrim($route->uri(), '/');

            if (str_starts_with($uri, 'api/v1/') || str_starts_with($uri, 'v1/')) {
                $route->middleware(EnsureIdentityAccountActive::class);
            }

            $isLegacyDeviceMutation = str_starts_with($uri, 'api/v1/devices')
                || str_starts_with($uri, 'v1/devices');
            $isCircleMutation = str_starts_with($uri, 'api/v1/circles')
                || str_starts_with($uri, 'v1/circles');

            $isSecurityMutation = str_contains($uri, 'auth/email-otp/verify')
                || str_contains($uri, 'auth/otp/verify')
                || (
                    ($isLegacyDeviceMutation || $isCircleMutation)
                    && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []
                );

            if ($isSecurityMutation) {
                $route->middleware(AuditSecurityMutation::class);
            }
        });
    }
}
