<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Broadcasts\NotificationRealtimeBroadcast;
use App\Modules\Notifications\Events\NotificationCreated;

final class BroadcastNotificationCreated
{
    public function handle(NotificationCreated $event): void
    {
        broadcast(new NotificationRealtimeBroadcast(
            $event->realtime['channel'],
            $event->realtime['event_name'],
            $event->realtime['payload'],
        ));
    }
}
