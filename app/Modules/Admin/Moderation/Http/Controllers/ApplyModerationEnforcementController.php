<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\ModerationReport;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use App\Modules\Admin\Moderation\Services\ModerationEnforcementService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ApplyModerationEnforcementController
{
    public function __invoke(Request $r, string $reportId, ModerationEnforcementService $s): JsonResponse
    {
        $a = $r->user();
        $session = $r->attributes->get('admin_session');
        if (! $a instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($r, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }
        $d = $r->validate(['action' => ['required', Rule::in((array) config('orbit_moderation.allowed_enforcements', []))], 'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'], 'feature' => ['nullable', 'string'], 'warning' => ['nullable', 'string', 'max:500'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $report = ModerationReport::query()->find($reportId);
        if (! $report) {
            return AdminApiResponse::error($r, 'Moderation report not found.', 'REPORT_NOT_FOUND', 404);
        }
        try {
            $e = $s->apply($report, $a, $session, $d, $r);
        } catch (ModerationDomainException $x) {
            return AdminApiResponse::error($r, $x->getMessage(), $x->errorCode, $x->status);
        }

        return AdminApiResponse::success($r, ['id' => (string) $e->id, 'action' => $e->action, 'target_type' => $e->target_type, 'target_id' => $e->target_id, 'status' => $e->status], 201, 'Enforcement applied.');
    }
}
