<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CircleInviteResource extends JsonResource
{
    public function __construct($resource, private readonly string $code)
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
            'code' => $this->code,
            'max_uses' => $this->resource->max_uses,
            'uses_count' => $this->resource->uses_count,
            'expires_at' => $this->resource->expires_at->toIso8601String(),
        ];
    }
}
