<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\ModerationReport;
use App\Modules\Admin\Moderation\Services\ModerationWorkflowService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AddModerationCaseNoteController
{
    public function __invoke(Request $r, string $reportId, ModerationWorkflowService $s): JsonResponse
    {
        $a = $r->user();
        $session = $r->attributes->get('admin_session');
        if (! $a instanceof AdminUser || ! $session instanceof AdminSession) {
            return AdminApiResponse::error($r, 'Administrator session unavailable.', 'ADMIN_SESSION_INVALID', 401);
        }
        $d = $r->validate(['note' => ['required', 'string', 'min:2', 'max:3000']]);
        $report = ModerationReport::query()->find($reportId);
        if (! $report) {
            return AdminApiResponse::error($r, 'Moderation report not found.', 'REPORT_NOT_FOUND', 404);
        }
        $n = $s->addNote($report, $a, $session, $d['note'], $r);

        return AdminApiResponse::success($r, ['id' => (string) $n->id, 'note' => $n->note, 'created_at' => $n->created_at?->toIso8601String()], 201, 'Moderation note created.');
    }
}
