<?php

declare(strict_types=1);

namespace App\Modules\Activity\Actions;

use App\Models\ActivityHiddenEvent;
use App\Models\User;
use App\Modules\Activity\Services\ActivityAccessService;

final readonly class HideActivityAction
{
    public function __construct(private ActivityAccessService $access) {}

    public function handle(User $user, string $activityId): void
    {
        $event = $this->access->findVisible($user, $activityId);

        ActivityHiddenEvent::query()->firstOrCreate(
            [
                'user_id' => $user->getKey(),
                'activity_event_id' => $event->id,
            ],
            ['hidden_at' => now()],
        );
    }
}
