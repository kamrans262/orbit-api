<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Http\Middleware;

use App\Models\ApiRequestMetric;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RecordApiTelemetry
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        try {
            if (Schema::hasTable('api_request_metrics')) {
                $route = $request->route();
                $name = $route?->uri() ?? $request->path();
                ApiRequestMetric::query()->create(['request_id' => $request->headers->get('X-Request-ID') ?: $request->attributes->get('admin_request_id'), 'method' => $request->method(), 'route' => mb_substr((string) $name, 0, 240), 'status_code' => $response->getStatusCode(), 'latency_ms' => (int) round((microtime(true) - $start) * 1000), 'is_admin' => str_starts_with($request->path(), 'api/admin/'), 'occurred_at' => now()]);
            }
        } catch (Throwable) {
        }

return $response;
    }
}
