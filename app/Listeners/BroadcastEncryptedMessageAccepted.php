<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\MessageEnvelope;
use App\Modules\Messaging\Events\EncryptedMessageAccepted;
use App\Modules\Realtime\Broadcasts\MessageEnvelopeAvailableBroadcast;

final class BroadcastEncryptedMessageAccepted
{
    public function handle(EncryptedMessageAccepted $event): void
    {
        MessageEnvelope::query()
            ->with('message')
            ->where('message_id', $event->messageId)
            ->orderBy('id')
            ->each(fn (MessageEnvelope $envelope) => MessageEnvelopeAvailableBroadcast::dispatch($envelope));
    }
}
