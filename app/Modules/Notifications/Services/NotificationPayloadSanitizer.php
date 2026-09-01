<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

final class NotificationPayloadSanitizer
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'message' => ['message_id', 'circle_id', 'sender_user_id', 'encrypted_preview', 'deep_link'],
        'moment' => ['moment_id', 'circle_id', 'author_user_id', 'media_type', 'deep_link'],
        'ping' => ['ping_id', 'circle_id', 'sender_user_id', 'ping_type', 'deep_link'],
        'sos' => ['sos_id', 'circle_id', 'originator_user_id', 'latitude', 'longitude', 'stage', 'action', 'deep_link'],
        'activity' => ['activity_id', 'circle_id', 'event_type', 'actor_user_id', 'deep_link'],
        'generic' => ['resource_id', 'circle_id', 'actor_user_id', 'deep_link'],
    ];

    public function sanitize(string $kind, array $payload): array
    {
        $family = explode('.', $kind, 2)[0];
        $allowed = self::ALLOWED[$family] ?? self::ALLOWED['generic'];
        $safe = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                $safe[$key] = $payload[$key];
            }
        }

        return $safe;
    }
}
