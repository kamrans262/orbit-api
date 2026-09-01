<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Messaging\Events\TypingIndicatorChanged;
use App\Modules\Realtime\Broadcasts\TypingIndicatorBroadcast;

final class BroadcastTypingIndicator
{
    public function handle(TypingIndicatorChanged $event): void
    {
        TypingIndicatorBroadcast::dispatch(
            circleId: $event->circleId,
            membershipId: $event->membershipId,
            userId: $event->userId,
            isTyping: $event->isTyping,
        );
    }
}
