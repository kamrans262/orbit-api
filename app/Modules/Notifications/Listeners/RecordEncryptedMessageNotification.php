<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use App\Modules\Notifications\Services\NotificationEventPayloadExtractor;
use Illuminate\Support\Facades\DB;

final readonly class RecordEncryptedMessageNotification
{
    public function __construct(
        private RouteNotificationAction $route,
        private NotificationEventPayloadExtractor $extractor,
    ) {}

    public function handle(object $event): void
    {
        foreach ($this->extractor->payloads($event) as $payload) {
            $target = $this->extractor->first($payload, ['recipient_user_id', 'target_user_id', 'recipient_id']);
            $recipientDeviceId = $this->extractor->first($payload, ['recipient_device_id']);

            if (! is_numeric($target) && $recipientDeviceId !== null) {
                $target = DB::table('devices')->where('id', (string) $recipientDeviceId)->value('user_id');
            }

            $messageId = $this->extractor->first($payload, ['message_id', 'id']);
            $circleId = $this->extractor->first($payload, ['circle_id']);
            $sender = $this->extractor->first($payload, ['sender_user_id', 'sender_id', 'user_id']);

            if (! is_numeric($target) || $messageId === null) {
                continue;
            }

            $this->route->handle(
                (int) $target,
                'message.received',
                'message:'.$messageId.':user:'.$target,
                [
                    'message_id' => (string) $messageId,
                    'circle_id' => $circleId !== null ? (string) $circleId : null,
                    'sender_user_id' => is_numeric($sender) ? (int) $sender : null,
                    'encrypted_preview' => $this->extractor->first($payload, ['encrypted_preview', 'preview_envelope']),
                    'deep_link' => $circleId !== null ? 'orbit://circles/'.$circleId.'/chat' : null,
                ],
                NotificationPriority::Normal,
                $circleId !== null ? (string) $circleId : null,
                $circleId !== null ? 'orbit://circles/'.$circleId.'/chat' : null,
            );
        }
    }
}
