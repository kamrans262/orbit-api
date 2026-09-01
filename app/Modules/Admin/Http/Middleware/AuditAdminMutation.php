<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Services\AdminAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditAdminMutation
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $admin = $request->user();
        $session = $request->attributes->get('admin_session');
        $this->audit->write(
            'admin.api.mutation',
            $admin instanceof AdminUser ? $admin : null,
            $session instanceof AdminSession ? $session : null,
            targetType: 'admin_route',
            targetId: $request->route()?->getName(),
            result: $response->getStatusCode() < 400 ? 'success' : 'failed',
            metadata: [
                'method' => strtoupper($request->method()),
                'status_code' => $response->getStatusCode(),
                'route_parameters' => $request->route()?->parameters() ?? [],
            ],
            request: $request,
        );

        return $response;
    }
}
