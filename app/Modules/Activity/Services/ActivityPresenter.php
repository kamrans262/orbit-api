<?php

declare(strict_types=1);

namespace App\Modules\Activity\Services;

use App\Models\ActivityEvent;

final class ActivityPresenter
{
    public function present(ActivityEvent $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->event_type,
            'circle_id' => $event->circle_id,
            'actor_user_id' => $event->actor_user_id,
            'source' => [
                'type' => $event->source_type,
                'id' => $event->source_id,
            ],
            'payload' => $event->payload ?? [],
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ];
    }
}
