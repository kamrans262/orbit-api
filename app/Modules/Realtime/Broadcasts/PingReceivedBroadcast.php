<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Broadcasts;

use App\Models\Ping;
use App\Modules\Realtime\Support\PingRealtimePayload;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PingReceivedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Ping $ping) {}

    public function broadcastOn(): PrivateChannel
    {
        $this->ping->loadMissing('recipientMembership');

        return new PrivateChannel('users.'.$this->ping->recipientMembership->user_id);
    }

    public function broadcastAs(): string
    {
        return 'ping.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ping' => PingRealtimePayload::make($this->ping),
        ];
    }
}
