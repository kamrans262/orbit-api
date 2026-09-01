<?php

declare(strict_types=1);

namespace App\Modules\Sos\Services;

use App\Models\SosEvent;
use App\Models\SosNotificationOutbox;
use Illuminate\Support\Facades\DB;

final class SosNotificationOutboxService
{
    public function queueActivation(SosEvent $event): void
    {
        foreach ($this->otherCircleMemberIds($event) as $userId) {
            $this->queue(
                $event,
                $userId,
                'push',
                'sos.activated',
                [
                    'sos_id' => $event->id,
                    'circle_id' => $event->circle_id,
                    'originator_user_id' => $event->user_id,
                    'latitude' => $event->last_latitude,
                    'longitude' => $event->last_longitude,
                    'deep_link' => 'orbit://sos/'.$event->id,
                ],
            );
        }
    }

    public function queueStageOne(SosEvent $event): void
    {
        foreach ($this->unengagedResponderIds($event) as $userId) {
            $this->queue(
                $event,
                $userId,
                'push',
                'sos.escalated.stage1',
                [
                    'sos_id' => $event->id,
                    'circle_id' => $event->circle_id,
                    'originator_user_id' => $event->user_id,
                    'deep_link' => 'orbit://sos/'.$event->id,
                ],
            );
        }
    }

    private function queue(SosEvent $event, int $targetUserId, string $channel, string $kind, array $payload): void
    {
        SosNotificationOutbox::query()->firstOrCreate(
            [
                'sos_event_id' => $event->id,
                'target_user_id' => $targetUserId,
                'channel' => $channel,
                'kind' => $kind,
            ],
            [
                'priority' => 'highest',
                'payload' => $payload,
                'status' => 'pending',
                'available_at' => now(),
                'attempts' => 0,
            ],
        );
    }

    private function otherCircleMemberIds(SosEvent $event): array
    {
        return DB::table('circle_members')
            ->where('circle_id', $event->circle_id)
            ->where('user_id', '!=', $event->user_id)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function unengagedResponderIds(SosEvent $event): array
    {
        return DB::table('sos_responders')
            ->where('sos_event_id', $event->id)
            ->where('status', '!=', 'engaged')
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
