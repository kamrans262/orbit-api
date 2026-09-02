<?php

declare(strict_types=1);

namespace App\Modules\Admin\BackendCompletion\Http\Middleware;

use App\Models\AdminUser;
use App\Modules\Admin\BackendCompletion\Services\AdminIpPolicyService;
use App\Modules\Admin\Support\AdminApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforceAdminIpAllowlist
{
    public function __construct(private AdminIpPolicyService $policies) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! str_starts_with($request->path(), 'api/admin/') || ! Schema::hasTable('admin_ip_policies')) {
            return $next($request);
        }

        $bearer = $request->bearerToken();
        $token = is_string($bearer) && $bearer !== '' ? PersonalAccessToken::findToken($bearer) : null;
        $admin = $token?->tokenable;

        if ($admin instanceof AdminUser && $token?->can('admin') && ! $this->policies->allows((int) $admin->id, $request->ip())) {
            return AdminApiResponse::error(
                $request,
                'Administrator access is not permitted from this network.',
                'ADMIN_IP_NOT_ALLOWED',
                403,
            );
        }

        return $next($request);
    }
}
