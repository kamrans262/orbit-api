<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use App\Modules\Notifications\Services\NotificationEventPayloadExtractor;

final readonly class RecordPingNotification
{
    public function __construct(
        private RouteNotificationAction $route,
        private NotificationEventPayloadExtractor $extractor,
    ) {}

    public function handle(object $event): void
    {
        foreach ($this->extractor->payloads($event) as $payload) {
            $target = $this->extractor->first($payload, ['recipient_user_id', 'target_user_id', 'recipient_id']);
            $pingId = $this->extractor->first($payload, ['ping_id', 'id']);
            $circleId = $this->extractor->first($payload, ['circle_id']);
            $sender = $this->extractor->first($payload, ['sender_user_id', 'sender_id', 'user_id']);

            if (! is_numeric($target) || $pingId === null) {
                continue;
            }

            $this->route->handle(
                (int) $target,
                'ping.received',
                'ping:'.$pingId.':user:'.$target,
                [
                    'ping_id' => (string) $pingId,
                    'circle_id' => $circleId !== null ? (string) $circleId : null,
                    'sender_user_id' => is_numeric($sender) ? (int) $sender : null,
                    'ping_type' => $this->extractor->first($payload, ['ping_type', 'type']),
                    'deep_link' => 'orbit://pings/'.$pingId,
                ],
                NotificationPriority::High,
                $circleId !== null ? (string) $circleId : null,
                'orbit://pings/'.$pingId,
            );
        }
    }
}
