<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\AdminRiskSignal;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Moderation\Services\AdminRiskService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ResolveRiskSignalController
{
    public function __invoke(Request $r, string $signalId, AdminRiskService $s): JsonResponse
    {
        $a = $r->user();
        $session = $r->attributes->get('admin_session');
        if (! $a instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($r, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }
        $d = $r->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $sig = AdminRiskSignal::query()->find($signalId);
        if (! $sig) {
            return AdminApiResponse::error($r, 'Risk signal not found.', 'RISK_SIGNAL_NOT_FOUND', 404);
        }
        $sig = $s->resolve($sig, $a, $session, $d['reason'], $r);

        return AdminApiResponse::success($r, ['id' => (string) $sig->id, 'resolved_at' => $sig->resolved_at?->toIso8601String()]);
    }
}
