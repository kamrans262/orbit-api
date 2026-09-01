<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Moments\Events\MomentPublished;
use App\Modules\Realtime\Broadcasts\MomentPublishedBroadcast;

final class BroadcastMomentPublished
{
    public function handle(MomentPublished $event): void
    {
        MomentPublishedBroadcast::dispatch($event->moment);
    }
}
