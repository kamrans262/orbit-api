<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Moments\Events\MomentViewed;
use App\Modules\Realtime\Broadcasts\MomentViewedBroadcast;

final class BroadcastMomentViewed
{
    public function handle(MomentViewed $event): void
    {
        MomentViewedBroadcast::dispatch(
            momentId: $event->moment->id,
            authorUserId: $event->moment->author_user_id,
            viewerUserId: $event->viewerUserId,
            anonymous: $event->anonymous,
            viewedAt: $event->viewedAt,
        );
    }
}
