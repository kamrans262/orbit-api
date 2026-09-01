<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Broadcasts;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class MomentViewedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $momentId,
        public readonly int $authorUserId,
        public readonly ?int $viewerUserId,
        public readonly bool $anonymous,
        public readonly string $viewedAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->authorUserId);
    }

    public function broadcastAs(): string
    {
        return 'moment.viewed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'moment_id' => $this->momentId,
            'viewer_user_id' => $this->anonymous ? null : $this->viewerUserId,
            'anonymous' => $this->anonymous,
            'viewed_at' => $this->viewedAt,
        ];
    }
}
