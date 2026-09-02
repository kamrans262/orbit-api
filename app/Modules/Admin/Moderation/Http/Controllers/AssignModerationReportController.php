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

final class AssignModerationReportController
{
    public function __invoke(Request $r, string $reportId, ModerationWorkflowService $s, ModerationPresenter $p): JsonResponse
    {
        $a = $r->user();
        $session = $r->attributes->get('admin_session');
        if (! $a instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($r, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }
        $d = $r->validate(['assigned_admin_id' => ['nullable', 'integer', 'exists:admin_users,id'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $report = ModerationReport::query()->find($reportId);
        if (! $report) {
            return AdminApiResponse::error($r, 'Moderation report not found.', 'REPORT_NOT_FOUND', 404);
        }
        $assignee = isset($d['assigned_admin_id']) ? AdminUser::query()->find((int) $d['assigned_admin_id']) : null;
        try {
            $report = $s->assign($report, $assignee, $a, $session, $d['reason'], $r);
        } catch (ModerationDomainException $e) {
            return AdminApiResponse::error($r, $e->getMessage(), $e->errorCode, $e->status);
        }

        return AdminApiResponse::success($r,$p->report($report));
    }
}
