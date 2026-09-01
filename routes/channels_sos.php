<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('orbit.circle.{circleId}', function (User $user, string $circleId): bool {
    return DB::table('circle_members')
        ->where('circle_id', $circleId)
        ->where('user_id', $user->getKey())
        ->exists();
});

Broadcast::channel('orbit.sos.{sosId}', function (User $user, string $sosId): bool {
    $event = DB::table('sos_events')->where('id', $sosId)->first(['user_id']);

    if (! $event) {
        return false;
    }

    if ((int) $event->user_id === (int) $user->getKey()) {
        return true;
    }

    return DB::table('sos_responders')
        ->where('sos_event_id', $sosId)
        ->where('user_id', $user->getKey())
        ->where('status', 'engaged')
        ->exists();
});
