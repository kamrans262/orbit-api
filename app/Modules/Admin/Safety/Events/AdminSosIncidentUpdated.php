<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AdminSosIncidentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<string,mixed> $payload */
    public function __construct(public readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.safety')];
    }

    public function broadcastAs(): string
    {
        return 'admin.sos.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
