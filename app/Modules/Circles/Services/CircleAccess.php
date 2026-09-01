<?php

declare(strict_types=1);

namespace App\Modules\Circles\Services;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Exceptions\CircleException;

final class CircleAccess
{
    public function findVisible(User $user, string $circleId): Circle
    {
        $circle = Circle::query()
            ->whereKey($circleId)
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id))
            ->first();

        return $circle ?? throw CircleException::notFound();
    }

    public function membership(User $user, Circle $circle): CircleMember
    {
        $membership = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->first();

        return $membership ?? throw CircleException::notFound();
    }

    public function member(Circle $circle, string $membershipId): CircleMember
    {
        $membership = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->whereKey($membershipId)
            ->first();

        return $membership ?? throw CircleException::memberNotFound();
    }

    public function assertActive(Circle $circle): void
    {
        if ($circle->isArchived() || $circle->isExpired()) {
            throw CircleException::archived();
        }
    }

    public function assertCanManage(CircleMember $membership): void
    {
        if (! $membership->role->canManageMembers()) {
            throw CircleException::forbidden();
        }
    }

    public function assertOwner(CircleMember $membership): void
    {
        if ($membership->role !== CircleRole::Owner) {
            throw CircleException::forbidden();
        }
    }
}
