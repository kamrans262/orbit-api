<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Sos\Broadcasts\SosRealtimeBroadcast;
use App\Modules\Sos\Events\SosLocationUpdated;

final class BroadcastSosLocationUpdated
{
    public function handle(SosLocationUpdated $event): void
    {
        broadcast(new SosRealtimeBroadcast(
            $event->realtime['channel'],
            $event->realtime['event_name'],
            $event->realtime['payload'],
        ));
    }
}
