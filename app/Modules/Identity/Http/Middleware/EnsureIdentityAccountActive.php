<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIdentityAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && Schema::hasColumn('users', 'account_deleted_at')
            && $user->getAttribute('account_deleted_at') !== null
        ) {
            return response()->json([
                'error' => [
                    'code' => 'account_deleted',
                    'message' => 'This Orbit account has been deleted.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
