<?php

declare(strict_types=1);

namespace App\Modules\Activity\Actions;

use App\Models\ActivityReport;
use App\Models\User;
use App\Modules\Activity\Services\ActivityAccessService;
use App\Modules\Admin\Moderation\Services\ModerationIntakeService;

final readonly class ReportActivityAction
{
    public function __construct(
        private ActivityAccessService $access,
        private ModerationIntakeService $moderation,
    ) {}

    public function handle(User $user, string $activityId, array $data): ActivityReport
    {
        $event = $this->access->findVisible($user, $activityId);

        $report = ActivityReport::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'activity_event_id' => $event->id],
            ['reason' => $data['reason'], 'details' => $data['details'] ?? null, 'status' => 'pending'],
        );

        $this->moderation->ingestActivityReport($report);

        return $report;
    }
}
