<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Models\CircleMember;
use App\Models\Message;
use App\Models\MessageReadReceipt;
use App\Models\MessagingPreference;
use App\Models\User;
use App\Modules\Messaging\Events\MessageRead;
use App\Modules\Messaging\Exceptions\MessagingException;

final class MarkMessageReadAction
{
    /** @return array{broadcasted: bool, duplicate: bool, read_at: string|null} */
    public function handle(User $user, string $circleId, string $messageId): array
    {
        $message = Message::query()
            ->whereKey($messageId)
            ->where('circle_id', $circleId)
            ->first();

        if ($message === null) {
            throw MessagingException::messageNotFound();
        }

        $isMember = CircleMember::query()
            ->where('circle_id', $circleId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isMember) {
            throw MessagingException::forbidden();
        }

        if ($message->sender_user_id === $user->id) {
            return ['broadcasted' => false, 'duplicate' => false, 'read_at' => null];
        }

        $enabled = MessagingPreference::query()
            ->where('user_id', $user->id)
            ->value('read_receipts_enabled');

        if ($enabled === false) {
            return ['broadcasted' => false, 'duplicate' => false, 'read_at' => null];
        }

        $receipt = MessageReadReceipt::query()->firstOrCreate(
            ['message_id' => $message->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );

        if (! $receipt->wasRecentlyCreated) {
            return [
                'broadcasted' => false,
                'duplicate' => true,
                'read_at' => $receipt->read_at->toIso8601String(),
            ];
        }

        MessageRead::dispatch(
            messageId: $message->id,
            circleId: $message->circle_id,
            senderUserId: $message->sender_user_id,
            readerUserId: $user->id,
            readAt: $receipt->read_at->toIso8601String(),
        );

        return [
            'broadcasted' => true,
            'duplicate' => false,
            'read_at' => $receipt->read_at->toIso8601String(),
        ];
    }
}
