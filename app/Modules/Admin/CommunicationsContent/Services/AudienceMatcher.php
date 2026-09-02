<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Services;

use App\Models\User;
use App\Models\UserRegionalProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AudienceMatcher
{
    public function matches(User $user, array $audience): bool
    {
        $explicit = collect([
            ...($audience['user_ids'] ?? []),
            ...($audience['custom_user_ids'] ?? []),
            ...($audience['cohort_user_ids'] ?? []),
        ])->map(fn ($id): int => (int) $id)->unique()->all();

        if (($audience['mode'] ?? 'all') !== 'all' && ! in_array((int) $user->getKey(), $explicit, true)) {
            return false;
        }
        if (($audience['mode'] ?? 'all') === 'all' && $explicit !== [] && ! in_array((int) $user->getKey(), $explicit, true)) {
            return false;
        }

        $plans = array_map('strval', $audience['plans'] ?? []);
        if ($plans !== [] && Schema::hasTable('user_subscriptions') && Schema::hasTable('billing_plans')) {
            $currentPlan = DB::table('user_subscriptions')
                ->join('billing_plans', 'billing_plans.id', '=', 'user_subscriptions.plan_id')
                ->where('user_subscriptions.user_id', $user->getKey())
                ->whereIn('user_subscriptions.status', ['active', 'cancel_pending'])
                ->where(fn ($q) => $q->whereNull('user_subscriptions.ends_at')->orWhere('user_subscriptions.ends_at', '>', now()))
                ->latest('user_subscriptions.started_at')
                ->value('billing_plans.slug');

            $effectivePlan = $currentPlan ? (string) $currentPlan : 'free';
            if (! in_array($effectivePlan, $plans, true)) {
                return false;
            }
        }

        $countries = array_map(fn ($v): string => strtoupper((string) $v), $audience['countries'] ?? []);
        $platforms = array_map(fn ($v): string => strtolower((string) $v), $audience['platforms'] ?? []);
        $versions = array_map('strval', $audience['app_versions'] ?? []);
        if ($countries !== [] || $platforms !== [] || $versions !== []) {
            $profile = UserRegionalProfile::query()->where('user_id', $user->getKey())->first();
            if (! $profile) {
                return false;
            }
            if ($countries !== [] && ! in_array(strtoupper((string) $profile->country_code), $countries, true)) {
                return false;
            }
            if ($platforms !== [] && ! in_array(strtolower((string) $profile->platform), $platforms, true)) {
                return false;
            }
            if ($versions !== [] && ! in_array((string) $profile->app_version, $versions, true)) {
                return false;
            }
        }

        return true;
    }
}
