<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ModerationRealtimeAuthController
{
    public function __invoke(Request $r): mixed
    {
        try {
            return Broadcast::auth($r);
        } catch (AccessDeniedHttpException) {
            return AdminApiResponse::error($r, 'You are not authorized to subscribe to this moderation channel.', 'ADMIN_REALTIME_FORBIDDEN', 403);
        }
    }
}
