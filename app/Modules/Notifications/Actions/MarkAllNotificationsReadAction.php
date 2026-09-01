<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Models\OrbitNotification;
use App\Models\User;

final class MarkAllNotificationsReadAction
{
    public function handle(User $user): int
    {
        return OrbitNotification::query()
            ->where('user_id', $user->getKey())
            ->where('in_app_visible', true)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }
}
