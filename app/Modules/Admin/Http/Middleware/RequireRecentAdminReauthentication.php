<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Models\AdminSession;
use App\Modules\Admin\Support\AdminApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRecentAdminReauthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->attributes->get('admin_session');
        $window = max(1, (int) config('orbit_admin.reauth_window_minutes', 10));

        if (! $session instanceof AdminSession || $session->reauthenticated_at === null || $session->reauthenticated_at->lt(now()->subMinutes($window))) {
            return AdminApiResponse::error($request, 'Recent administrator reauthentication is required for this action.', 'ADMIN_REAUTH_REQUIRED', 428);
        }

        return $next($request);
    }
}
