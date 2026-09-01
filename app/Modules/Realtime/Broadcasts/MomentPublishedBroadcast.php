<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Broadcasts;

use App\Models\Moment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MomentPublishedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Moment $moment) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('circles.'.$this->moment->circle_id);
    }

    public function broadcastAs(): string
    {
        return 'moment.published';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->moment->loadMissing(['author', 'mediaAsset']);

        return [
            'moment' => [
                'id' => $this->moment->id,
                'circle_id' => $this->moment->circle_id,
                'author_user_id' => $this->moment->author_user_id,
                'media_asset_id' => $this->moment->media_asset_id,
                'media_kind' => $this->moment->mediaAsset->kind->value,
                'expires_at' => $this->moment->expires_at->toIso8601String(),
                'created_at' => $this->moment->created_at?->toIso8601String(),
            ],
        ];
    }
}
