<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Resources;

use App\Models\Circle;
use App\Models\CircleMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CircleResource extends JsonResource
{
    public function __construct(Circle $resource, private readonly CircleMember $membership)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'type' => $this->resource->type->value,
            'my_role' => $this->membership->role->value,
            'my_membership_id' => $this->membership->id,
            'member_count' => $this->resource->memberships_count ?? null,
            'expires_at' => $this->resource->expires_at?->toIso8601String(),
            'archived_at' => $this->resource->archived_at?->toIso8601String(),
            'is_archived' => $this->resource->isArchived(),
            'is_expired' => $this->resource->isExpired(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
