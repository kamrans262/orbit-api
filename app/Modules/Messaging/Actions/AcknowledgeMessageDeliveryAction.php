<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Models\MessageDeliveryReceipt;
use App\Models\MessageEnvelope;
use App\Models\User;
use App\Modules\Messaging\Events\EncryptedMessageDelivered;
use App\Modules\Messaging\Exceptions\MessagingException;
use Illuminate\Support\Facades\DB;

final class AcknowledgeMessageDeliveryAction
{
    /** @return array{message_id: string, envelope_id: string, delivered_at: string, duplicate: bool} */
    public function handle(User $user, string $envelopeId): array
    {
        $result = DB::transaction(function () use ($user, $envelopeId): array {
            $existingReceipt = MessageDeliveryReceipt::query()
                ->where('envelope_id', $envelopeId)
                ->first();

            if ($existingReceipt !== null) {
                if ($existingReceipt->recipient_user_id !== $user->id) {
                    throw MessagingException::forbidden();
                }

                return [
                    'message_id' => $existingReceipt->message_id,
                    'envelope_id' => $existingReceipt->envelope_id,
                    'delivered_at' => $existingReceipt->delivered_at->toIso8601String(),
                    'duplicate' => true,
                    'broadcast' => null,
                ];
            }

            $envelope = MessageEnvelope::query()
                ->with('message')
                ->where('envelope_id', $envelopeId)
                ->lockForUpdate()
                ->first();

            if ($envelope === null) {
                throw MessagingException::envelopeNotFound();
            }

            if ($envelope->recipient_user_id !== $user->id) {
                throw MessagingException::forbidden();
            }

            $deliveredAt = now();

            MessageDeliveryReceipt::query()->create([
                'envelope_id' => $envelope->envelope_id,
                'message_id' => $envelope->message_id,
                'recipient_user_id' => $envelope->recipient_user_id,
                'recipient_device_id' => $envelope->recipient_device_id,
                'delivered_at' => $deliveredAt,
            ]);

            $broadcast = [
                'message_id' => $envelope->message_id,
                'sender_user_id' => $envelope->message->sender_user_id,
                'recipient_user_id' => $envelope->recipient_user_id,
                'recipient_device_id' => $envelope->recipient_device_id,
                'delivered_at' => $deliveredAt->toIso8601String(),
            ];

            $envelope->delete();

            return [
                'message_id' => $broadcast['message_id'],
                'envelope_id' => $envelopeId,
                'delivered_at' => $broadcast['delivered_at'],
                'duplicate' => false,
                'broadcast' => $broadcast,
            ];
        });

        if ($result['broadcast'] !== null) {
            EncryptedMessageDelivered::dispatch(
                messageId: $result['broadcast']['message_id'],
                senderUserId: $result['broadcast']['sender_user_id'],
                recipientUserId: $result['broadcast']['recipient_user_id'],
                recipientDeviceId: $result['broadcast']['recipient_device_id'],
                deliveredAt: $result['broadcast']['delivered_at'],
            );
        }

        unset($result['broadcast']);

        return $result;
    }
}
