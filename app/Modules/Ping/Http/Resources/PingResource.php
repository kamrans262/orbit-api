<?php

declare(strict_types=1);

namespace App\Modules\Ping\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'circle' => [
                'id' => $this->resource->circle->id,
                'name' => $this->resource->circle->name,
            ],
            'sender' => [
                'membership_id' => $this->resource->senderMembership->id,
                'user_id' => $this->resource->senderMembership->user->id,
                'name' => $this->resource->senderMembership->user->name,
            ],
            'recipient' => [
                'membership_id' => $this->resource->recipientMembership->id,
                'user_id' => $this->resource->recipientMembership->user->id,
                'name' => $this->resource->recipientMembership->user->name,
            ],
            'status' => $this->resource->effectiveStatus()->value,
            'response_type' => $this->resource->response_type?->value,
            'expires_at' => $this->resource->expires_at->toIso8601String(),
            'responded_at' => $this->resource->responded_at?->toIso8601String(),
            'dismissed_at' => $this->resource->dismissed_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
