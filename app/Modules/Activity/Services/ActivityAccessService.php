<?php

declare(strict_types=1);

namespace App\Modules\Activity\Services;

use App\Models\ActivityEvent;
use App\Models\User;
use App\Modules\Activity\Exceptions\ActivityException;
use Illuminate\Support\Facades\DB;

final class ActivityAccessService
{
    public function findVisible(User $user, string $activityId): ActivityEvent
    {
        $event = ActivityEvent::query()
            ->whereKey($activityId)
            ->whereNull('removed_at')
            ->first();

        if (! $event || ! $this->sharesCircle($user, $event->circle_id)) {
            throw ActivityException::itemUnavailable();
        }

        return $event;
    }

    public function sharesCircle(User $user, string $circleId): bool
    {
        return DB::table('circle_members')
            ->where('circle_id', $circleId)
            ->where('user_id', $user->getKey())
            ->exists();
    }
}
