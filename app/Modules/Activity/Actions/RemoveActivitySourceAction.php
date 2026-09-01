<?php

declare(strict_types=1);

namespace App\Modules\Activity\Actions;

use App\Models\ActivityEvent;
use App\Modules\Activity\Events\ActivityItemRemoved;

final class RemoveActivitySourceAction
{
    public function handle(string $sourceType, string $sourceId): int
    {
        $events = ActivityEvent::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereNull('removed_at')
            ->get();

        foreach ($events as $event) {
            $event->forceFill(['removed_at' => now()])->save();

            event(new ActivityItemRemoved([
                'channel' => 'orbit.circle.'.$event->circle_id,
                'event_name' => 'activity.removed',
                'payload' => [
                    'id' => $event->id,
                    'source' => [
                        'type' => $event->source_type,
                        'id' => $event->source_id,
                    ],
                ],
            ]));
        }

        return $events->count();
    }
}
