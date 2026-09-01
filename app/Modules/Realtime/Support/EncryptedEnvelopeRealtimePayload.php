<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Support;

use App\Models\MessageEnvelope;

final class EncryptedEnvelopeRealtimePayload
{
    /** @return array<string, mixed> */
    public static function make(MessageEnvelope $envelope): array
    {
        $envelope->loadMissing('message');

        return [
            'server_cursor' => $envelope->id,
            'envelope_id' => $envelope->envelope_id,
            'message_id' => $envelope->message_id,
            'circle_id' => $envelope->message->circle_id,
            'sender_user_id' => $envelope->message->sender_user_id,
            'sender_device_id' => $envelope->message->sender_device_id,
            'recipient_device_id' => $envelope->recipient_device_id,
            'type' => $envelope->message->type->value,
            'ciphertext' => $envelope->ciphertext,
            'encrypted_preview' => $envelope->encrypted_preview,
            'created_at' => $envelope->created_at?->toIso8601String(),
            'expires_at' => $envelope->expires_at->toIso8601String(),
        ];
    }
}
