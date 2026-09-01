<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\OrbitNotification;
use App\Models\User;

final readonly class NotificationFeedService
{
    public function __construct(private NotificationPresenter $presenter) {}

    public function list(User $user, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $notifications = OrbitNotification::query()
            ->where('user_id', $user->getKey())
            ->where('in_app_visible', true)
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        return [
            'data' => $notifications->map(fn (OrbitNotification $notification): array => $this->presenter->present($notification))->all(),
            'meta' => [
                'limit' => $limit,
                'unread_count' => OrbitNotification::query()->where('user_id', $user->getKey())->where('in_app_visible', true)->whereNull('read_at')->count(),
            ],
        ];
    }
}
