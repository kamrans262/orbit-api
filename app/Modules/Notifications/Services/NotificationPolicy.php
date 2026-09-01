<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\CircleNotificationPreference;
use Carbon\CarbonImmutable;

final readonly class NotificationPolicy
{
    public function __construct(private NotificationPreferencesService $preferences) {}

    /** @return array{in_app: bool, push: bool, silent: bool} */
    public function resolve(int $userId, string $kind, ?string $circleId): array
    {
        if (str_starts_with($kind, 'sos.')) {
            return ['in_app' => true, 'push' => true, 'silent' => false];
        }

        $prefs = $this->preferences->forUser($userId);
        $enabled = match (true) {
            str_starts_with($kind, 'message.') => (bool) $prefs->messages_enabled,
            str_starts_with($kind, 'moment.') => (bool) $prefs->moments_enabled,
            str_starts_with($kind, 'ping.') => (bool) $prefs->pings_enabled,
            str_starts_with($kind, 'activity.') => (bool) $prefs->activity_enabled,
            default => true,
        };

        if (! $enabled) {
            return ['in_app' => false, 'push' => false, 'silent' => false];
        }

        $silent = $this->circleIsSilent($userId, $circleId) || $this->isQuietHours($prefs);

        return [
            'in_app' => (bool) $prefs->in_app_enabled,
            'push' => (bool) $prefs->push_enabled,
            'silent' => $silent,
        ];
    }

    private function circleIsSilent(int $userId, ?string $circleId): bool
    {
        if ($circleId === null || $circleId === '') {
            return false;
        }

        $preference = CircleNotificationPreference::query()
            ->where('user_id', $userId)
            ->where('circle_id', $circleId)
            ->first();

        if (! $preference) {
            return false;
        }

        return (bool) $preference->silent
            || ($preference->muted_until !== null && $preference->muted_until->isFuture());
    }

    private function isQuietHours(object $prefs): bool
    {
        if (! $prefs->quiet_hours_enabled || ! $prefs->quiet_hours_start || ! $prefs->quiet_hours_end) {
            return false;
        }

        try {
            $now = CarbonImmutable::now($prefs->timezone ?: 'UTC');
        } catch (\Throwable) {
            $now = CarbonImmutable::now('UTC');
        }

        $start = CarbonImmutable::parse($now->format('Y-m-d').' '.$prefs->quiet_hours_start, $now->timezone);
        $end = CarbonImmutable::parse($now->format('Y-m-d').' '.$prefs->quiet_hours_end, $now->timezone);

        if ($end->lessThanOrEqualTo($start)) {
            return $now->greaterThanOrEqualTo($start) || $now->lessThan($end);
        }

        return $now->betweenIncluded($start, $end);
    }
}
