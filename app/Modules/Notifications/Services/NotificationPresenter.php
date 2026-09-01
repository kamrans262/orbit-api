<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\OrbitNotification;

final class NotificationPresenter
{
    public function present(OrbitNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->kind,
            'priority' => $notification->priority,
            'summary' => $notification->summary,
            'circle_id' => $notification->circle_id,
            'payload' => $notification->payload ?? [],
            'deep_link' => $notification->deep_link,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
