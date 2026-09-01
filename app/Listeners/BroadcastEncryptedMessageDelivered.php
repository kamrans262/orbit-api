<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Messaging\Events\EncryptedMessageDelivered;
use App\Modules\Realtime\Broadcasts\MessageDeliveredBroadcast;

final class BroadcastEncryptedMessageDelivered
{
    public function handle(EncryptedMessageDelivered $event): void
    {
        MessageDeliveredBroadcast::dispatch(
            messageId: $event->messageId,
            senderUserId: $event->senderUserId,
            recipientUserId: $event->recipientUserId,
            recipientDeviceId: $event->recipientDeviceId,
            deliveredAt: $event->deliveredAt,
        );
    }
}
