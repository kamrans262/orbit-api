<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Device;
use App\Models\Message;
use App\Models\MessageDeliveryReceipt;
use App\Models\MessageEnvelope;
use App\Models\User;
use App\Modules\Messaging\Enums\MessageType;
use App\Modules\Messaging\Events\EncryptedMessageAccepted;
use App\Modules\Messaging\Exceptions\MessagingException;
use Illuminate\Support\Facades\DB;

final class SendEncryptedMessageAction
{
    /**
     * @param array{
     *   message_id: string,
     *   sender_device_id: string,
     *   type: string,
     *   client_sent_at?: string|null,
     *   envelopes: list<array{
     *     envelope_id: string,
     *     recipient_device_id: string,
     *     ciphertext: string,
     *     encrypted_preview?: string|null
     *   }>
     * } $data
     * @return array{message: Message, duplicate: bool}
     */
    public function handle(User $user, string $circleId, array $data): array
    {
        $circle = Circle::query()->available()->find($circleId);

        if ($circle === null) {
            throw MessagingException::circleNotFound();
        }

        $membership = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null) {
            throw MessagingException::circleNotFound();
        }

        if (! $membership->can_message) {
            throw MessagingException::messagingDisabled();
        }

        $senderDevice = Device::query()
            ->whereKey($data['sender_device_id'])
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        if ($senderDevice === null) {
            throw MessagingException::invalidSenderDevice();
        }

        $existing = Message::query()->whereKey($data['message_id'])->first();

        if ($existing !== null) {
            if (
                $existing->circle_id !== $circle->id
                || $existing->sender_user_id !== $user->id
                || $existing->sender_device_id !== $senderDevice->id
            ) {
                throw MessagingException::messageIdConflict();
            }

            return [
                'message' => $existing->loadCount('envelopes'),
                'duplicate' => true,
            ];
        }

        $expectedDevices = Device::query()
            ->select('devices.*')
            ->join('circle_members', 'circle_members.user_id', '=', 'devices.user_id')
            ->where('circle_members.circle_id', $circle->id)
            ->where('circle_members.can_message', true)
            ->whereNull('devices.revoked_at')
            ->whereNotNull('devices.public_identity_key')
            ->where('devices.id', '!=', $senderDevice->id)
            ->orderBy('devices.id')
            ->get();

        $expectedDeviceIds = $expectedDevices->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();

        if ($expectedDeviceIds === []) {
            throw MessagingException::noRecipientDevices();
        }

        $providedDeviceIds = collect($data['envelopes'])
            ->pluck('recipient_device_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        if (count($providedDeviceIds) !== count(array_unique($providedDeviceIds))) {
            throw MessagingException::recipientDevicesChanged($expectedDeviceIds);
        }

        sort($providedDeviceIds);
        $sortedExpected = $expectedDeviceIds;
        sort($sortedExpected);

        if ($providedDeviceIds !== $sortedExpected) {
            throw MessagingException::recipientDevicesChanged($expectedDeviceIds);
        }

        $envelopeIds = collect($data['envelopes'])->pluck('envelope_id')->all();

        if (count($envelopeIds) !== count(array_unique($envelopeIds))) {
            throw MessagingException::envelopeIdConflict();
        }

        $envelopeIdAlreadyUsed = MessageEnvelope::query()->whereIn('envelope_id', $envelopeIds)->exists()
            || MessageDeliveryReceipt::query()->whereIn('envelope_id', $envelopeIds)->exists();

        if ($envelopeIdAlreadyUsed) {
            throw MessagingException::envelopeIdConflict();
        }

        $deviceOwners = $expectedDevices->pluck('user_id', 'id');
        $retentionDays = max(1, (int) config('orbit.messaging.server_retention_days', 30));
        $expiresAt = now()->addDays($retentionDays);

        $message = DB::transaction(function () use (
            $user,
            $circle,
            $senderDevice,
            $data,
            $deviceOwners,
            $expiresAt,
        ): Message {
            $message = Message::query()->create([
                'id' => $data['message_id'],
                'circle_id' => $circle->id,
                'sender_user_id' => $user->id,
                'sender_device_id' => $senderDevice->id,
                'type' => MessageType::from($data['type']),
                'client_sent_at' => $data['client_sent_at'] ?? null,
                'expires_at' => $expiresAt,
            ]);

            foreach ($data['envelopes'] as $envelopeData) {
                MessageEnvelope::query()->create([
                    'envelope_id' => $envelopeData['envelope_id'],
                    'message_id' => $message->id,
                    'recipient_user_id' => (int) $deviceOwners[$envelopeData['recipient_device_id']],
                    'recipient_device_id' => $envelopeData['recipient_device_id'],
                    'ciphertext' => $envelopeData['ciphertext'],
                    'encrypted_preview' => $envelopeData['encrypted_preview'] ?? null,
                    'expires_at' => $expiresAt,
                ]);
            }

            return $message->loadCount('envelopes');
        });

        EncryptedMessageAccepted::dispatch($message->id);

        return ['message' => $message, 'duplicate' => false];
    }
}
