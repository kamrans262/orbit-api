<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Moments\Events\MomentDeleted;
use App\Modules\Realtime\Broadcasts\MomentDeletedBroadcast;

final class BroadcastMomentDeleted
{
    public function handle(MomentDeleted $event): void
    {
        MomentDeletedBroadcast::dispatch(
            momentId: $event->moment->id,
            circleId: $event->moment->circle_id,
        );
    }
}
