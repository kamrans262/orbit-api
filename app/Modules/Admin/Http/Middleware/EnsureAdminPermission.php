<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Models\AdminUser;
use App\Modules\Admin\Support\AdminApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = $request->user();
        if (! $admin instanceof AdminUser || ! $admin->hasPermission($permission)) {
            return AdminApiResponse::error($request, 'You do not have permission to perform this administrator action.', 'ADMIN_FORBIDDEN', 403);
        }

        return $next($request);
    }
}
