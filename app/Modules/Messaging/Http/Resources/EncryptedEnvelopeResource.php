<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EncryptedEnvelopeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'server_cursor' => $this->resource->id,
            'envelope_id' => $this->resource->envelope_id,
            'message_id' => $this->resource->message_id,
            'circle_id' => $this->resource->message->circle_id,
            'sender_user_id' => $this->resource->message->sender_user_id,
            'sender_device_id' => $this->resource->message->sender_device_id,
            'recipient_device_id' => $this->resource->recipient_device_id,
            'type' => $this->resource->message->type->value,
            'ciphertext' => $this->resource->ciphertext,
            'encrypted_preview' => $this->resource->encrypted_preview,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'expires_at' => $this->resource->expires_at->toIso8601String(),
        ];
    }
}
