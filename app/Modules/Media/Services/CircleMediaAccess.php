<?php

declare(strict_types=1);

namespace App\Modules\Media\Services;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Modules\Media\Exceptions\MediaException;

final class CircleMediaAccess
{
    public function membership(User $user, string $circleId, bool $requireMessaging = false): CircleMember
    {
        $circle = Circle::query()
            ->available()
            ->whereKey($circleId)
            ->first();

        if ($circle === null) {
            throw MediaException::circleNotFound();
        }

        $membership = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null) {
            throw MediaException::circleNotFound();
        }

        if ($requireMessaging && ! $membership->can_message) {
            throw MediaException::messagingDisabled();
        }

        return $membership;
    }
}
