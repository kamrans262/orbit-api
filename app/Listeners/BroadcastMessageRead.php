<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Messaging\Events\MessageRead;
use App\Modules\Realtime\Broadcasts\MessageReadBroadcast;

final class BroadcastMessageRead
{
    public function handle(MessageRead $event): void
    {
        MessageReadBroadcast::dispatch(
            messageId: $event->messageId,
            circleId: $event->circleId,
            senderUserId: $event->senderUserId,
            readerUserId: $event->readerUserId,
            readAt: $event->readAt,
        );
    }
}
