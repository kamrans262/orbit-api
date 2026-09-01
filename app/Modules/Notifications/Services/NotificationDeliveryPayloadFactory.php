<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\OrbitNotification;
use App\Modules\Notifications\Enums\NotificationPriority;

final class NotificationDeliveryPayloadFactory
{
    public function forDevice(OrbitNotification $notification, string $platform, bool $silent): array
    {
        $sos = str_starts_with($notification->kind, 'sos.');
        $high = in_array($notification->priority, [NotificationPriority::High->value, NotificationPriority::Highest->value], true);

        $common = [
            'notification_id' => $notification->id,
            'kind' => $notification->kind,
            'priority' => $notification->priority,
            'silent' => $silent,
            'summary' => $notification->summary,
            'deep_link' => $notification->deep_link,
            'data' => $notification->payload ?? [],
        ];

        if ($platform === 'ios') {
            $common['apns'] = [
                'push_type' => 'alert',
                'priority' => $high ? 10 : 5,
                'interruption_level' => $sos ? 'time-sensitive' : 'active',
            ];
        } elseif ($platform === 'android') {
            $common['fcm'] = [
                'priority' => $high ? 'HIGH' : 'NORMAL',
                'channel' => $sos ? 'sos' : 'default',
            ];
        }

        return $common;
    }
}
