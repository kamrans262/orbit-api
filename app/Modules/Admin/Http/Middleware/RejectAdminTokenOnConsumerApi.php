<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class RejectAdminTokenOnConsumerApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/admin/*')) {
            return $next($request);
        }

        $plainTextToken = $request->bearerToken();
        $accessToken = is_string($plainTextToken) && $plainTextToken !== ''
            ? PersonalAccessToken::findToken($plainTextToken)
            : null;

        if ($accessToken?->tokenable instanceof AdminUser) {
            return response()->json([
                'success' => false,
                'message' => 'Consumer authentication is required.',
                'code' => 'CONSUMER_AUTHENTICATION_REQUIRED',
            ], 401);
        }

        return $next($request);
    }
}
