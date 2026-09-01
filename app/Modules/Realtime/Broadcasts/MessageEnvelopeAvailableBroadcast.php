<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Broadcasts;

use App\Models\MessageEnvelope;
use App\Modules\Realtime\Support\EncryptedEnvelopeRealtimePayload;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MessageEnvelopeAvailableBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly MessageEnvelope $envelope) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('devices.'.$this->envelope->recipient_device_id);
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['envelope' => EncryptedEnvelopeRealtimePayload::make($this->envelope)];
    }
}
