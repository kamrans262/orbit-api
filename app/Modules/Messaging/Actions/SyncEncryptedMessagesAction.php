<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Device;
use App\Models\MessageEnvelope;
use App\Models\User;
use App\Modules\Messaging\Exceptions\MessagingException;
use Illuminate\Support\Collection;

final class SyncEncryptedMessagesAction
{
    /** @return array{envelopes: Collection<int, MessageEnvelope>, next_cursor: int, has_more: bool} */
    public function handle(
        User $user,
        string $deviceId,
        int $afterId,
        int $limit,
        ?string $circleId = null,
    ): array {
        $device = Device::query()
            ->whereKey($deviceId)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        if ($device === null) {
            throw MessagingException::invalidDevice();
        }

        if ($circleId !== null) {
            $circle = Circle::query()->available()->find($circleId);

            if ($circle === null) {
                throw MessagingException::circleNotFound();
            }

            $isMember = CircleMember::query()
                ->where('circle_id', $circle->id)
                ->where('user_id', $user->id)
                ->exists();

            if (! $isMember) {
                throw MessagingException::circleNotFound();
            }
        }

        $limit = min(max(1, $limit), (int) config('orbit.messaging.sync_limit_max', 200));

        $query = MessageEnvelope::query()
            ->with('message')
            ->where('recipient_device_id', $device->id)
            ->where('id', '>', $afterId)
            ->where('expires_at', '>', now())
            ->orderBy('id');

        if ($circleId !== null) {
            $query->whereHas('message', fn ($messageQuery) => $messageQuery->where('circle_id', $circleId));
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;

        if ($hasMore) {
            $rows = $rows->take($limit)->values();
        }

        $nextCursor = $rows->isEmpty() ? $afterId : (int) $rows->last()->id;

        return [
            'envelopes' => $rows,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }
}
