<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Sos\Broadcasts\SosRealtimeBroadcast;
use App\Modules\Sos\Events\SosActivated;

final class BroadcastSosActivated
{
    public function handle(SosActivated $event): void
    {
        broadcast(new SosRealtimeBroadcast(
            $event->realtime['channel'],
            $event->realtime['event_name'],
            $event->realtime['payload'],
        ));
    }
}
