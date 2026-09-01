<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id', 'push_enabled', 'in_app_enabled', 'messages_enabled', 'moments_enabled',
        'pings_enabled', 'activity_enabled', 'quiet_hours_enabled', 'quiet_hours_start',
        'quiet_hours_end', 'timezone',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'push_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'messages_enabled' => 'boolean',
            'moments_enabled' => 'boolean',
            'pings_enabled' => 'boolean',
            'activity_enabled' => 'boolean',
            'quiet_hours_enabled' => 'boolean',
        ];
    }
}
