<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Services;

use App\Models\AdminUser;
use App\Models\UserContactEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ContactHistoryService
{
    public function record(
        int $userId,
        string $kind,
        string $channel,
        string $direction,
        ?string $subject = null,
        ?string $summary = null,
        ?string $sourceType = null,
        string|int|null $sourceId = null,
        ?AdminUser $admin = null,
        array $metadata = [],
    ): UserContactEvent {
        return UserContactEvent::query()->create([
            'user_id' => $userId,
            'channel' => $channel,
            'kind' => $kind,
            'direction' => $direction,
            'subject' => $subject !== null ? mb_substr($subject, 0, 160) : null,
            'summary' => $summary !== null ? mb_substr($summary, 0, 500) : null,
            'source_type' => $sourceType,
            'source_id' => $sourceId !== null ? (string) $sourceId : null,
            'actor_admin_id' => $admin?->id,
            'metadata' => $this->sanitize($metadata),
            'occurred_at' => now(),
        ]);
    }

    public function recordOnce(
        int $userId,
        string $kind,
        string $channel,
        string $direction,
        ?string $subject = null,
        ?string $summary = null,
        ?string $sourceType = null,
        string|int|null $sourceId = null,
        ?AdminUser $admin = null,
        array $metadata = [],
    ): UserContactEvent {
        if ($sourceType !== null && $sourceId !== null) {
            $existing = UserContactEvent::query()
                ->where('user_id', $userId)
                ->where('kind', $kind)
                ->where('source_type', $sourceType)
                ->where('source_id', (string) $sourceId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->record(
            $userId, $kind, $channel, $direction, $subject, $summary,
            $sourceType, $sourceId, $admin, $metadata,
        );
    }

    public function paginateForUser(int $userId, int $perPage = 25): LengthAwarePaginator
    {
        return UserContactEvent::query()
            ->where('user_id', $userId)
            ->latest('occurred_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    private function sanitize(array $metadata): array
    {
        $blockedFragments = [
            'password', 'token', 'secret', 'authorization', 'otp', 'code',
            'private_key', 'plaintext', 'ciphertext', 'encrypted_key',
        ];

        $clean = [];
        foreach ($metadata as $key => $value) {
            $lower = strtolower((string) $key);
            if (collect($blockedFragments)->contains(fn (string $fragment): bool => str_contains($lower, $fragment))) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $clean[$key] = mb_substr($value, 0, 500);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
