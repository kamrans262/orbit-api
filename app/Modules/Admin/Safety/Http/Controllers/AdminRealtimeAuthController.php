<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Http\Controllers;

use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AdminRealtimeAuthController
{
    public function __invoke(Request $request): mixed
    {
        try {
            return Broadcast::auth($request);
        } catch (AccessDeniedHttpException) {
            return AdminApiResponse::error(
                $request,
                'You are not authorized to subscribe to this administrator realtime channel.',
                'ADMIN_REALTIME_FORBIDDEN',
                403,
            );
        }
    }
}
