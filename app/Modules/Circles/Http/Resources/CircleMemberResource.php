<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CircleMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'membership_id' => $this->resource->id,
            'role' => $this->resource->role->value,
            'location_mode' => $this->resource->location_mode->value,
            'can_ping' => $this->resource->can_ping,
            'can_message' => $this->resource->can_message,
            'can_view_moments' => $this->resource->can_view_moments,
            'activity_visibility' => $this->resource->activity_visibility,
            'joined_at' => $this->resource->joined_at?->toIso8601String(),
            'user' => [
                'id' => $this->resource->user->id,
                'name' => $this->resource->user->name,
                'email' => $this->resource->user->email,
            ],
        ];
    }
}
