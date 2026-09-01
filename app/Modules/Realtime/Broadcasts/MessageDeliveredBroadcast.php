<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Broadcasts;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class MessageDeliveredBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $messageId,
        public readonly int $senderUserId,
        public readonly int $recipientUserId,
        public readonly string $recipientDeviceId,
        public readonly string $deliveredAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->senderUserId);
    }

    public function broadcastAs(): string
    {
        return 'message.delivered';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'recipient_user_id' => $this->recipientUserId,
            'recipient_device_id' => $this->recipientDeviceId,
            'delivered_at' => $this->deliveredAt,
        ];
    }
}
