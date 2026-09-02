<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Middleware;

use App\Modules\Admin\CommunicationsContent\Services\RegionalPlatformService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforceMaintenanceMode
{
    public function __construct(private RegionalPlatformService $platform) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('maintenance_windows') || $this->isAlwaysAvailable($request)) {
            return $next($request);
        }

        $service = $this->serviceFor($request);
        $window = $this->platform->activeMaintenance(app()->environment(), $service);
        if (! $window) {
            return $next($request);
        }

        if ($window->read_only && in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => $window->message,
            'code' => 'MAINTENANCE_ACTIVE',
            'data' => [
                'service' => $window->service,
                'read_only' => (bool) $window->read_only,
                'title' => $window->title,
                'expected_restoration' => $window->expected_restoration,
                'ends_at' => $window->ends_at?->toIso8601String(),
                'sos_available' => true,
            ],
        ], 503);
    }

    private function isAlwaysAvailable(Request $request): bool
    {
        $path = $request->path();

        return str_starts_with($path, 'api/admin/')
            || str_starts_with($path, 'api/v1/sos')
            || str_starts_with($path, 'api/v1/auth')
            || str_starts_with($path, 'api/v1/identity')
            || str_starts_with($path, 'api/v1/notifications')
            || str_starts_with($path, 'api/v1/platform/config')
            || str_starts_with($path, 'api/v1/platform/runtime')
            || str_starts_with($path, 'api/v1/support')
            || str_starts_with($path, 'api/v1/appeals');
    }

    private function serviceFor(Request $request): string
    {
        $path = $request->path();
        foreach ([
            'messaging' => ['messages', 'messaging'],
            'moments' => ['moments'],
            'presence' => ['presence'],
            'media' => ['media'],
            'circles' => ['circles'],
            'notifications' => ['notifications'],
            'billing' => ['subscription', 'payments'],
            'advertising' => ['ads'],
        ] as $service => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($path, $needle)) {
                    return $service;
                }
            }
        }

        return 'global';
    }
}
