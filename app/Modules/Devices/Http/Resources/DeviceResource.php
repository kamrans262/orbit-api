<?php

declare(strict_types=1);

namespace App\Modules\Devices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'client_device_id' => $this->resource->client_device_id,
            'name' => $this->resource->name,
            'platform' => $this->resource->platform,
            'app_version' => $this->resource->app_version,
            'os_version' => $this->resource->os_version,
            'has_public_identity_key' => filled($this->resource->public_identity_key),
            'has_push_token' => filled($this->resource->push_token),
            'status' => $this->resource->revoked_at === null ? 'active' : 'revoked',
            'last_seen_at' => $this->resource->last_seen_at?->toIso8601String(),
            'revoked_at' => $this->resource->revoked_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
