<?php

declare(strict_types=1);

namespace App\Modules\Activity\Listeners;

use App\Modules\Activity\Broadcasts\ActivityRealtimeBroadcast;
use App\Modules\Activity\Events\ActivityItemRemoved;

final class BroadcastActivityItemRemoved
{
    public function handle(ActivityItemRemoved $event): void
    {
        broadcast(new ActivityRealtimeBroadcast(
            $event->realtime['channel'],
            $event->realtime['event_name'],
            $event->realtime['payload'],
        ));
    }
}
