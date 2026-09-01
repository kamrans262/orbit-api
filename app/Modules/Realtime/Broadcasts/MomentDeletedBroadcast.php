<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Broadcasts;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class MomentDeletedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $momentId,
        public readonly string $circleId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('circles.'.$this->circleId);
    }

    public function broadcastAs(): string
    {
        return 'moment.deleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'moment_id' => $this->momentId,
            'circle_id' => $this->circleId,
        ];
    }
}
