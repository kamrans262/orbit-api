<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Broadcasts;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class CirclePresenceUpdatedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $presence
     */
    public function __construct(
        public readonly string $circleId,
        public readonly string $membershipId,
        public readonly int $userId,
        public readonly array $presence,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('circles.'.$this->circleId);
    }

    public function broadcastAs(): string
    {
        return 'presence.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'circle_id' => $this->circleId,
            'membership_id' => $this->membershipId,
            'user_id' => $this->userId,
            'presence' => $this->presence,
        ];
    }
}
