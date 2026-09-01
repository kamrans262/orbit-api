<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Broadcasts;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class TypingIndicatorBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $circleId,
        public readonly string $membershipId,
        public readonly int $userId,
        public readonly bool $isTyping,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('circles.'.$this->circleId);
    }

    public function broadcastAs(): string
    {
        return 'typing.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'circle_id' => $this->circleId,
            'membership_id' => $this->membershipId,
            'user_id' => $this->userId,
            'is_typing' => $this->isTyping,
            'expires_in_seconds' => max(1, (int) config('orbit.messaging.typing_expiry_seconds', 5)),
        ];
    }
}
