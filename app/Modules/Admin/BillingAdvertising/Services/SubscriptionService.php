<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Services;

use App\Models\AdminUser;
use App\Models\BillingPlan;
use App\Models\BillingPromotion;
use App\Models\BillingPromotionRedemption;
use App\Models\User;
use App\Models\UserSubscription;
use App\Modules\Admin\BillingAdvertising\Exceptions\BillingAdvertisingDomainException;
use App\Modules\Admin\PrivacySupport\Services\ContactHistoryService;
use Illuminate\Support\Facades\DB;

final class SubscriptionService
{
    public function __construct(private readonly BillingCatalogService $catalog, private readonly ContactHistoryService $contacts) {}

    public function current(User $user): UserSubscription
    {
        $existing = UserSubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'cancel_pending'])
            ->latest('started_at')
            ->first();

        if ($existing !== null && ($existing->ends_at === null || $existing->ends_at->isFuture())) {
            return $existing;
        }

        $this->catalog->syncDefaults();
        $free = BillingPlan::query()->where('slug', 'free')->firstOrFail();

        return UserSubscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $free->id,
            'status' => 'active',
            'source' => 'system',
            'provider' => 'manual',
            'price_amount_minor' => 0,
            'price_currency' => 'USD',
            'billing_interval' => 'monthly',
            'complimentary' => true,
            'started_at' => now(),
        ]);
    }

    public function changePlan(User $user, BillingPlan $plan, string $interval, string $currency, AdminUser $admin, bool $complimentary = false, ?int $durationDays = null, ?BillingPromotion $promotion = null): UserSubscription
    {
        if ($plan->status !== 'active') {
            throw new BillingAdvertisingDomainException('BILLING_PLAN_INACTIVE', 422, 'The selected billing plan is not active.');
        }

        $price = null;
        if (! $complimentary && $plan->slug !== 'free') {
            $price = $this->catalog->activePrice($plan, $interval, $currency);
            if ($price === null) {
                throw new BillingAdvertisingDomainException('BILLING_PRICE_NOT_CONFIGURED', 409, 'No active price is configured for this plan, interval, and currency.');
            }
        }

        return DB::transaction(function () use ($user, $plan, $interval, $currency, $admin, $complimentary, $durationDays, $promotion, $price): UserSubscription {
            UserSubscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'cancel_pending'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'ends_at' => now()]);

            $amount = (int) ($price?->amount_minor ?? 0);
            if ($promotion !== null) {
                $amount = $this->discountedAmount($amount, $currency, $promotion);
            }

            $subscription = UserSubscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'source' => $promotion !== null ? 'promotion' : ($complimentary ? 'complimentary' : 'admin'),
                'provider' => $price?->provider ?? 'manual',
                'price_amount_minor' => $amount,
                'price_currency' => strtoupper($currency),
                'billing_interval' => $interval,
                'complimentary' => $complimentary,
                'promotion_id' => $promotion?->id,
                'created_by_admin_id' => $admin->id,
                'started_at' => now(),
                'current_period_end' => $durationDays !== null ? now()->addDays($durationDays) : null,
                'ends_at' => $durationDays !== null ? now()->addDays($durationDays) : null,
            ]);

            if ($promotion !== null) {
                BillingPromotionRedemption::query()->create([
                    'promotion_id' => $promotion->id,
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'redeemed_at' => now(),
                ]);
                $promotion->increment('redemptions_count');
            }

            $this->contacts->record(
                $user->id, 'subscription.plan_changed', 'system', 'outbound', 'Subscription updated',
                'Your Orbit subscription was updated.', 'user_subscription', $subscription->id, $admin,
                ['plan' => $plan->slug, 'status' => $subscription->status],
            );

            return $subscription;
        });
    }

    public function extend(UserSubscription $subscription, int $days, AdminUser $admin): UserSubscription
    {
        if (! in_array($subscription->status, ['active', 'cancel_pending'], true)) {
            throw new BillingAdvertisingDomainException('SUBSCRIPTION_NOT_ACTIVE', 409, 'Only an active subscription can be extended.');
        }
        $base = $subscription->ends_at?->isFuture() ? $subscription->ends_at : now();
        $subscription->forceFill(['ends_at' => $base->addDays($days), 'current_period_end' => $base->addDays($days)])->save();
        $this->contacts->record((int) $subscription->user_id, 'subscription.extended', 'system', 'outbound', 'Subscription extended', 'Your Orbit subscription was extended.', 'user_subscription', $subscription->id, $admin, ['days' => $days]);

        return $subscription->refresh();
    }

    public function cancel(UserSubscription $subscription, bool $immediate, AdminUser $admin): UserSubscription
    {
        if (! in_array($subscription->status, ['active', 'cancel_pending'], true)) {
            throw new BillingAdvertisingDomainException('SUBSCRIPTION_NOT_ACTIVE', 409, 'Subscription is not active.');
        }
        if ($immediate) {
            $subscription->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'ends_at' => now(), 'cancel_at' => null])->save();
        } else {
            $cancelAt = $subscription->current_period_end ?? $subscription->ends_at ?? now()->addMonth();
            $subscription->forceFill(['status' => 'cancel_pending', 'cancel_at' => $cancelAt])->save();
        }
        $this->contacts->record((int) $subscription->user_id, 'subscription.cancellation_changed', 'system', 'outbound', 'Subscription cancellation', 'Your Orbit subscription cancellation status changed.', 'user_subscription', $subscription->id, $admin, ['status' => $subscription->status]);

        return $subscription->refresh();
    }

    public function restore(UserSubscription $subscription, AdminUser $admin): UserSubscription
    {
        if ($subscription->status === 'active') {
            return $subscription;
        }
        if ($subscription->ends_at !== null && $subscription->ends_at->isPast() && $subscription->status !== 'cancel_pending') {
            throw new BillingAdvertisingDomainException('SUBSCRIPTION_EXPIRED', 409, 'An expired subscription cannot be restored without creating a new plan assignment.');
        }
        $subscription->forceFill(['status' => 'active', 'cancel_at' => null, 'cancelled_at' => null])->save();
        $this->contacts->record((int) $subscription->user_id, 'subscription.restored', 'system', 'outbound', 'Subscription restored', 'Your Orbit subscription was restored.', 'user_subscription', $subscription->id, $admin, ['status' => 'active']);

        return $subscription->refresh();
    }

    public function eligiblePromotion(string $code, User $user): BillingPromotion
    {
        $promotion = BillingPromotion::query()->where('code', strtoupper($code))->where('status', 'active')->first();
        if ($promotion === null || ($promotion->starts_at !== null && $promotion->starts_at->isFuture()) || ($promotion->ends_at !== null && $promotion->ends_at->isPast())) {
            throw new BillingAdvertisingDomainException('PROMOTION_NOT_AVAILABLE', 404, 'Promotion is not available.');
        }
        if ($promotion->max_redemptions !== null && $promotion->redemptions_count >= $promotion->max_redemptions) {
            throw new BillingAdvertisingDomainException('PROMOTION_EXHAUSTED', 409, 'Promotion redemption limit has been reached.');
        }
        if (BillingPromotionRedemption::query()->where('promotion_id', $promotion->id)->where('user_id', $user->id)->exists()) {
            throw new BillingAdvertisingDomainException('PROMOTION_ALREADY_USED', 409, 'Promotion has already been used by this account.');
        }

        return $promotion;
    }

    private function discountedAmount(int $amount, string $currency, BillingPromotion $promotion): int
    {
        if ($promotion->percent_off !== null) {
            return max(0, (int) round($amount * (100 - $promotion->percent_off) / 100));
        }
        if ($promotion->amount_off_minor !== null && ($promotion->currency === null || strtoupper($promotion->currency) === strtoupper($currency))) {
            return max(0, $amount - (int) $promotion->amount_off_minor);
        }

        return $amount;
    }
}
