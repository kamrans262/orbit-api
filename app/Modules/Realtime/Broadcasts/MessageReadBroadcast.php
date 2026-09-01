<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Broadcasts;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class MessageReadBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $messageId,
        public readonly string $circleId,
        public readonly int $senderUserId,
        public readonly int $readerUserId,
        public readonly string $readAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->senderUserId);
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'circle_id' => $this->circleId,
            'reader_user_id' => $this->readerUserId,
            'read_at' => $this->readAt,
        ];
    }
}
