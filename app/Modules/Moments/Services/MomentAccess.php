<?php

declare(strict_types=1);

namespace App\Modules\Moments\Services;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Moments\Exceptions\MomentException;

final class MomentAccess
{
    public function member(User $user, string $circleId): CircleMember
    {
        $circle = Circle::query()
            ->available()
            ->whereKey($circleId)
            ->first();

        if ($circle === null) {
            throw MomentException::circleNotFound();
        }

        $membership = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->first();

        return $membership ?? throw MomentException::circleNotFound();
    }

    public function viewer(User $user, string $circleId): CircleMember
    {
        $membership = $this->member($user, $circleId);

        if (! $membership->can_view_moments) {
            throw MomentException::viewingDisabled();
        }

        return $membership;
    }

    public function publisher(User $user, string $circleId): CircleMember
    {
        $membership = $this->member($user, $circleId);

        if ($membership->role === CircleRole::Restricted) {
            throw MomentException::publishingRestricted();
        }

        return $membership;
    }
}
