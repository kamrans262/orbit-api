<?php

declare(strict_types=1);

namespace App\Modules\Circles\Actions;

use App\Models\CircleInvite;
use App\Models\CircleMember;
use App\Models\User;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Circles\Exceptions\CircleException;
use Illuminate\Support\Facades\DB;

final class JoinCircleAction
{
    public function handle(User $user, string $code): CircleMember
    {
        return DB::transaction(function () use ($user, $code): CircleMember {
            $invite = CircleInvite::query()
                ->where('code_hash', CreateCircleInviteAction::hashCode($code))
                ->lockForUpdate()
                ->first();

            if ($invite === null || ! $invite->isUsable()) {
                throw CircleException::invalidInvite();
            }

            $invite->load('circle');
            $circle = $invite->circle;

            if ($circle->isArchived() || $circle->isExpired()) {
                throw CircleException::invalidInvite();
            }

            $existingMembership = CircleMember::query()
                ->where('circle_id', $circle->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingMembership !== null) {
                return $existingMembership;
            }

            $membership = CircleMember::query()->create([
                'circle_id' => $circle->id,
                'user_id' => $user->id,
                'role' => CircleRole::Member,
                'location_mode' => LocationMode::Hidden,
                'joined_at' => now(),
            ]);

            $invite->increment('uses_count');

            return $membership;
        });
    }
}
