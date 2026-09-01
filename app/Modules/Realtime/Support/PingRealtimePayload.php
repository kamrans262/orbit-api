<?php

declare(strict_types=1);

namespace App\Modules\Realtime\Support;

use App\Models\Ping;

final class PingRealtimePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Ping $ping): array
    {
        $ping->loadMissing([
            'circle',
            'senderMembership.user',
            'recipientMembership.user',
        ]);

        return [
            'id' => $ping->id,
            'circle' => [
                'id' => $ping->circle->id,
                'name' => $ping->circle->name,
            ],
            'sender' => [
                'membership_id' => $ping->senderMembership->id,
                'user_id' => $ping->senderMembership->user->id,
                'name' => $ping->senderMembership->user->name,
            ],
            'recipient' => [
                'membership_id' => $ping->recipientMembership->id,
                'user_id' => $ping->recipientMembership->user->id,
                'name' => $ping->recipientMembership->user->name,
            ],
            'status' => $ping->effectiveStatus()->value,
            'response_type' => $ping->response_type?->value,
            'expires_at' => $ping->expires_at->toIso8601String(),
            'responded_at' => $ping->responded_at?->toIso8601String(),
            'dismissed_at' => $ping->dismissed_at?->toIso8601String(),
            'created_at' => $ping->created_at?->toIso8601String(),
        ];
    }
}
