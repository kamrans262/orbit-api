<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Ping\Events\PingSent;
use App\Modules\Realtime\Broadcasts\PingReceivedBroadcast;

final class BroadcastPingSent
{
    public function handle(PingSent $event): void
    {
        PingReceivedBroadcast::dispatch($event->ping);
    }
}
