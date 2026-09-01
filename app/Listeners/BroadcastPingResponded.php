<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Ping\Events\PingResponded;
use App\Modules\Realtime\Broadcasts\PingRespondedBroadcast;

final class BroadcastPingResponded
{
    public function handle(PingResponded $event): void
    {
        PingRespondedBroadcast::dispatch($event->ping);
    }
}
