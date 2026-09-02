<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Modules\Admin\Moderation\Services\ModerationDirectoryService;
use App\Modules\Admin\Moderation\Services\ModerationPresenter;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowModerationReportController
{
    public function __invoke(Request $r, string $reportId, ModerationDirectoryService $s, ModerationPresenter $p): JsonResponse
    {
        try {
            $report = $s->report($reportId);
        } catch (ModelNotFoundException) {
            return AdminApiResponse::error($r, 'Moderation report not found.', 'REPORT_NOT_FOUND', 404);
        }

        return AdminApiResponse::success($r, $p->report($report, true));
    }
}
