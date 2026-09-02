<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Services;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Models\UserRegionalProfile;

final class FeatureFlagService
{
    public function evaluated(User $user, string $environment = 'production'): array
    {
        $profile = UserRegionalProfile::query()->where('user_id', $user->id)->first();
        $result = [];
        foreach (FeatureFlag::query()->where('environment', $environment)->whereNull('archived_at')->get() as $flag) {
            $result[$flag->key] = $this->enabled($flag, $user, $profile);
        }
        ksort($result);

        return $result;
    }

    public function enabled(FeatureFlag $flag, User $user, ?UserRegionalProfile $profile = null): bool
    {
        if ($flag->status !== 'enabled') {
            return false;
        } if ($flag->starts_at?->isFuture() || $flag->ends_at?->isPast()) {
            return false;
        }
        $t = $flag->targeting ?? [];
        if (isset($t['user_ids']) && ! in_array((int) $user->id, array_map('intval', (array) $t['user_ids']), true)) {
            return false;
        }
        if (isset($t['countries']) && ! in_array(strtoupper((string) $profile?->country_code), array_map('strtoupper', (array) $t['countries']), true)) {
            return false;
        }
        if (isset($t['platforms']) && ! in_array(strtolower((string) $profile?->platform), array_map('strtolower', (array) $t['platforms']), true)) {
            return false;
        }
        if (isset($t['app_versions']) && ! in_array((string) $profile?->app_version, (array) $t['app_versions'], true)) {
            return false;
        }
        if ($flag->rollout_percentage >= 100) {
            return true;
        } if ($flag->rollout_percentage <= 0) {
            return (bool) $flag->default_enabled;
        }
        $bucket = abs(crc32($flag->key.':'.$user->id)) % 100;

        return $bucket < $flag->rollout_percentage;
    }
}
