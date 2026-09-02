<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class AudienceResolver
{
    /** @return list<int> */
    public function userIds(array $audience, int $limit = 100000): array
    {
        $query = User::query()->select('users.id');

        if (Schema::hasColumn('users', 'account_deleted_at')) {
            $query->whereNull('users.account_deleted_at');
        }

        $explicit = collect([
            ...($audience['user_ids'] ?? []),
            ...($audience['custom_user_ids'] ?? []),
            ...($audience['cohort_user_ids'] ?? []),
        ])->map(fn ($id): int => (int) $id)->filter(fn (int $id): bool => $id > 0)->unique()->values()->all();

        if (($audience['mode'] ?? 'all') !== 'all') {
            $query->whereIn('users.id', $explicit === [] ? [-1] : $explicit);
        } elseif ($explicit !== []) {
            $query->whereIn('users.id', $explicit);
        }

        $plans = array_values(array_filter(array_map('strval', $audience['plans'] ?? [])));
        if ($plans !== [] && Schema::hasTable('user_subscriptions') && Schema::hasTable('billing_plans')) {
            $includesFree = in_array('free', $plans, true);

            $matchingSubscriptions = function ($sub) use ($plans): void {
                $sub->select('user_subscriptions.user_id')
                    ->from('user_subscriptions')
                    ->join('billing_plans', 'billing_plans.id', '=', 'user_subscriptions.plan_id')
                    ->whereIn('billing_plans.slug', $plans)
                    ->whereIn('user_subscriptions.status', ['active', 'cancel_pending'])
                    ->where(fn ($q) => $q->whereNull('user_subscriptions.ends_at')->orWhere('user_subscriptions.ends_at', '>', now()));
            };

            if ($includesFree) {
                $activeSubscriptionUsers = function ($sub): void {
                    $sub->select('user_subscriptions.user_id')
                        ->from('user_subscriptions')
                        ->whereIn('user_subscriptions.status', ['active', 'cancel_pending'])
                        ->where(fn ($q) => $q->whereNull('user_subscriptions.ends_at')->orWhere('user_subscriptions.ends_at', '>', now()));
                };

                $query->where(function (Builder $q) use ($matchingSubscriptions, $activeSubscriptionUsers): void {
                    $q->whereIn('users.id', $matchingSubscriptions)
                        ->orWhereNotIn('users.id', $activeSubscriptionUsers);
                });
            } else {
                $query->whereIn('users.id', $matchingSubscriptions);
            }
        }

        if (Schema::hasTable('user_regional_profiles')) {
            $countries = array_map(fn ($v): string => strtoupper((string) $v), $audience['countries'] ?? []);
            $platforms = array_map(fn ($v): string => strtolower((string) $v), $audience['platforms'] ?? []);
            $versions = array_map('strval', $audience['app_versions'] ?? []);

            if ($countries !== [] || $platforms !== [] || $versions !== []) {
                $query->whereIn('users.id', function ($sub) use ($countries, $platforms, $versions): void {
                    $sub->select('user_id')->from('user_regional_profiles');
                    if ($countries !== []) {
                        $sub->whereIn('country_code', $countries);
                    }
                    if ($platforms !== []) {
                        $sub->whereIn('platform', $platforms);
                    }
                    if ($versions !== []) {
                        $sub->whereIn('app_version', $versions);
                    }
                });
            }
        }

        return $query->distinct()->orderBy('users.id')->limit(max(1, min($limit, 100000)))->pluck('users.id')->map(fn ($id): int => (int) $id)->all();
    }

    public function matchesUser(int $userId, array $audience): bool
    {
        if (($audience['mode'] ?? 'all') === 'all' && count(array_filter($audience)) === 0) {
            return true;
        }

        return in_array($userId, $this->userIds($audience, 100000), true);
    }
}
