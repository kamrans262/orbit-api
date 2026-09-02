<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AdminOperationalAlertRaised implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.operations')];
    }

    public function broadcastAs(): string
    {
        return 'admin.operations.alert';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
