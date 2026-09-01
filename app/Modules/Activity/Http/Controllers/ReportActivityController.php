<?php

declare(strict_types=1);

namespace App\Modules\Activity\Http\Controllers;

use App\Modules\Activity\Actions\ReportActivityAction;
use App\Modules\Activity\Http\Requests\ReportActivityRequest;
use Illuminate\Http\JsonResponse;

final class ReportActivityController
{
    public function __invoke(
        ReportActivityRequest $request,
        string $activityId,
        ReportActivityAction $report,
    ): JsonResponse {
        $record = $report->handle($request->user(), $activityId, $request->validated());

        return response()->json([
            'data' => [
                'id' => $record->id,
                'activity_id' => $record->activity_event_id,
                'status' => $record->status,
            ],
        ], 202);
    }
}
