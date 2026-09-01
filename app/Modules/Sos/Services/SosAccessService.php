<?php

declare(strict_types=1);

namespace App\Modules\Sos\Services;

use App\Models\SosEvent;
use App\Models\User;
use App\Modules\Sos\Exceptions\SosException;
use Illuminate\Support\Facades\DB;

final class SosAccessService
{
    public function assertCircleMember(User $user, string $circleId): void
    {
        $circleIsAvailable = DB::table('circles')
            ->where('id', $circleId)
            ->whereNull('archived_at')
            ->exists();

        $isMember = DB::table('circle_members')
            ->where('circle_id', $circleId)
            ->where('user_id', $user->getKey())
            ->exists();

        if (! $circleIsAvailable || ! $isMember) {
            throw SosException::circleUnavailable();
        }
    }

    public function assertEventMember(User $user, SosEvent $event): void
    {
        $isMember = DB::table('circle_members')
            ->where('circle_id', $event->circle_id)
            ->where('user_id', $user->getKey())
            ->exists();

        if (! $isMember) {
            throw SosException::eventUnavailable();
        }
    }
}
