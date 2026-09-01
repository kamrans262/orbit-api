<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'circle_id' => $this->resource->circle_id,
            'sender_user_id' => $this->resource->sender_user_id,
            'sender_device_id' => $this->resource->sender_device_id,
            'type' => $this->resource->type->value,
            'client_sent_at' => $this->resource->client_sent_at?->toIso8601String(),
            'pending_envelope_count' => (int) ($this->resource->envelopes_count ?? 0),
            'expires_at' => $this->resource->expires_at->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
