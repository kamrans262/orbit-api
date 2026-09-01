<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Sos\Broadcasts\SosRealtimeBroadcast;
use App\Modules\Sos\Events\SosResponderEngaged;

final class BroadcastSosResponderEngaged
{
    public function handle(SosResponderEngaged $event): void
    {
        broadcast(new SosRealtimeBroadcast(
            $event->realtime['channel'],
            $event->realtime['event_name'],
            $event->realtime['payload'],
        ));
    }
}
