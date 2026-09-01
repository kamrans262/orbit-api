<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Models\OrbitNotification;
use App\Models\User;
use App\Modules\Notifications\Exceptions\NotificationException;

final class MarkNotificationReadAction
{
    public function handle(User $user, string $notificationId): OrbitNotification
    {
        $notification = OrbitNotification::query()
            ->whereKey($notificationId)
            ->where('user_id', $user->getKey())
            ->where('in_app_visible', true)
            ->first();

        if (! $notification) {
            throw NotificationException::unavailable();
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return $notification;
    }
}
