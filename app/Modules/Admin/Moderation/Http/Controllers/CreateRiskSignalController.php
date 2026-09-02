<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Admin\Moderation\Services\AdminRiskService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateRiskSignalController
{
    public function __invoke(Request $r, string $userId, AdminRiskService $s): JsonResponse
    {
        $a = $r->user();
        $session = $r->attributes->get('admin_session');
        if (! $a instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($r, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }
        $u = ctype_digit($userId) ? User::query()->find((int) $userId) : null;
        if (! $u) {
            return AdminApiResponse::error($r, 'Risk profile user not found.', 'RISK_USER_NOT_FOUND', 404);
        }
        $d = $r->validate(['type' => ['required', Rule::in(['mass_behavior', 'sos_misuse', 'rate_limit_abuse', 'suspicious_device', 'auth_anomaly', 'report_received', 'other'])], 'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])], 'metadata' => ['nullable', 'array'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $sig = $s->manual($u, $a, $session, $d['type'], $d['severity'], $d['metadata'] ?? [], $d['reason'], $r);

        return AdminApiResponse::success($r, ['id' => (string) $sig->id, 'type' => $sig->type, 'severity' => $sig->severity], 201, 'Risk signal created.');
    }
}
