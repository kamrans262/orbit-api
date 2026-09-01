<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Models\CircleNotificationPreference;
use App\Models\User;
use App\Modules\Notifications\Exceptions\NotificationException;
use Illuminate\Support\Facades\DB;

final class UpdateCircleNotificationPreferenceAction
{
    public function handle(User $user, string $circleId, array $data): CircleNotificationPreference
    {
        $member = DB::table('circle_members')
            ->where('circle_id', $circleId)
            ->where('user_id', $user->getKey())
            ->exists();

        if (! $member) {
            throw NotificationException::circleUnavailable();
        }

        return CircleNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->getKey(), 'circle_id' => $circleId],
            $data,
        );
    }
}
