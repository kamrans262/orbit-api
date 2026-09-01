<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Messaging\Events\TypingIndicatorChanged;
use App\Modules\Messaging\Exceptions\MessagingException;
use Illuminate\Support\Facades\Cache;

final class SendTypingIndicatorAction
{
    /** @return array{broadcasted: bool, suppressed_reason: string|null} */
    public function handle(User $user, string $circleId, bool $isTyping): array
    {
        $circle = Circle::query()->available()->find($circleId);

        if ($circle === null) {
            throw MessagingException::circleNotFound();
        }

        $membership = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null) {
            throw MessagingException::circleNotFound();
        }

        if (! $membership->can_message) {
            throw MessagingException::messagingDisabled();
        }

        if ((bool) ($user->global_ghost_mode ?? false) || $membership->location_mode === LocationMode::Ghost) {
            return ['broadcasted' => false, 'suppressed_reason' => 'ghost_mode'];
        }

        $cacheKey = 'orbit:typing:'.$user->id.':'.$circle->id;

        if ($isTyping) {
            $throttleSeconds = max(1, (int) config('orbit.messaging.typing_throttle_seconds', 3));
            $allowed = Cache::add($cacheKey, true, now()->addSeconds($throttleSeconds));

            if (! $allowed) {
                return ['broadcasted' => false, 'suppressed_reason' => 'throttled'];
            }
        } else {
            Cache::forget($cacheKey);
        }

        TypingIndicatorChanged::dispatch(
            circleId: $circle->id,
            membershipId: $membership->id,
            userId: $user->id,
            isTyping: $isTyping,
        );

        return ['broadcasted' => true, 'suppressed_reason' => null];
    }
}
