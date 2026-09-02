<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\ModerationReport;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use App\Modules\Admin\Moderation\Services\ModerationPresenter;
use App\Modules\Admin\Moderation\Services\ModerationWorkflowService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class UpdateModerationReportWorkflowController
{
    public function __invoke(Request $r, string $reportId, ModerationWorkflowService $s, ModerationPresenter $p): JsonResponse
    {
        $a = $r->user();
        $session = $r->attributes->get('admin_session');
        if (! $a instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($r, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }
        $d = $r->validate(['status' => ['required', Rule::in(['new', 'triaged', 'assigned', 'under_review', 'actioned', 'escalated', 'closed'])], 'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical'])], 'risk_score' => ['nullable', 'integer', 'min:0', 'max:100'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $report = ModerationReport::query()->find($reportId);
        if (! $report) {
            return AdminApiResponse::error($r, 'Moderation report not found.', 'REPORT_NOT_FOUND', 404);
        }
        try {
            $report = $s->transition($report, $d['status'], $d['priority'] ?? null, $d['risk_score'] ?? null, $a, $session, $d['reason'], $r);
        } catch (ModerationDomainException $e) {
            return AdminApiResponse::error($r, $e->getMessage(), $e->errorCode, $e->status);
        }

        return AdminApiResponse::success($r,$p->report($report));
    }
}
