<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\NotificationPreference;

final class NotificationPreferencesService
{
    public function forUser(int $userId): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate(
            ['user_id' => $userId],
            [
                'push_enabled' => true,
                'in_app_enabled' => true,
                'messages_enabled' => true,
                'moments_enabled' => true,
                'pings_enabled' => true,
                'activity_enabled' => true,
                'quiet_hours_enabled' => false,
                'timezone' => 'UTC',
            ],
        );
    }
}
