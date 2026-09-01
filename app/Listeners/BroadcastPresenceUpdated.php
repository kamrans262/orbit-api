<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Modules\Presence\Events\PresenceUpdated;
use App\Modules\Presence\Services\PresencePresenter;
use App\Modules\Realtime\Broadcasts\CirclePresenceUpdatedBroadcast;

final class BroadcastPresenceUpdated
{
    public function __construct(private readonly PresencePresenter $presenter) {}

    public function handle(PresenceUpdated $event): void
    {
        $user = User::query()
            ->with([
                'presenceState',
                'circleMemberships.user',
            ])
            ->find($event->userId);

        if ($user === null) {
            return;
        }

        foreach ($user->circleMemberships as $membership) {
            CirclePresenceUpdatedBroadcast::dispatch(
                circleId: $membership->circle_id,
                membershipId: $membership->id,
                userId: $user->id,
                presence: $this->presenter->forCircle($membership, $user->presenceState),
            );
        }
    }
}
