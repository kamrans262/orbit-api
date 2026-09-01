<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Models\AdminUser;
use App\Modules\Admin\Support\AdminApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();
        $accessToken = is_string($plainTextToken) && $plainTextToken !== ''
            ? PersonalAccessToken::findToken($plainTextToken)
            : null;

        $admin = $accessToken?->tokenable;
        if (! $accessToken || ! $admin instanceof AdminUser || ! $accessToken->can('admin')) {
            return AdminApiResponse::error($request, 'Administrator authentication is required.', 'ADMIN_UNAUTHENTICATED', 401);
        }

        if ($accessToken->expires_at?->isPast()) {
            $accessToken->delete();

            return AdminApiResponse::error($request, 'The administrator session has expired.', 'ADMIN_SESSION_EXPIRED', 401);
        }

        if (! $admin->isOperationallyActive() || ! $admin->hasPermission('admin.access')) {
            return AdminApiResponse::error($request, 'Administrator access is disabled or expired.', 'ADMIN_ACCESS_DENIED', 403);
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();
        $admin->withAccessToken($accessToken);
        $request->setUserResolver(fn (): AdminUser => $admin);
        $request->attributes->set('admin_access_token_id', (int) $accessToken->getKey());

        return $next($request);
    }
}
