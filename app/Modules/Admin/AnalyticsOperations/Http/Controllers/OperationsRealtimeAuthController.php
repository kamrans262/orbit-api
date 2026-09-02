<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Http\Controllers;

use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class OperationsRealtimeAuthController
{
    public function __invoke(Request $request): mixed
    {
        try {
            return Broadcast::auth($request);
        } catch (AccessDeniedHttpException) {
            return AdminApiResponse::error($request, 'You are not authorized to subscribe to this administrator operations channel.', 'ADMIN_REALTIME_FORBIDDEN', 403);
        }
    }
}
