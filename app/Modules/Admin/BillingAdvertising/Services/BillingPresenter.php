<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Services;

use App\Models\BillingPlan;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\UserSubscription;

final class BillingPresenter
{
    public function __construct(private readonly BillingCatalogService $catalog) {}

    public function subscription(UserSubscription $subscription): array
    {
        $plan = BillingPlan::query()->find($subscription->plan_id);

        return [
            'id' => $subscription->id,
            'user_id' => (int) $subscription->user_id,
            'status' => $subscription->status,
            'plan' => $plan ? ['id' => $plan->id, 'slug' => $plan->slug, 'name' => $plan->name] : null,
            'billing_interval' => $subscription->billing_interval,
            'price' => ['amount_minor' => (int) $subscription->price_amount_minor, 'currency' => $subscription->price_currency],
            'complimentary' => (bool) $subscription->complimentary,
            'started_at' => $subscription->started_at?->toIso8601String(),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'cancel_at' => $subscription->cancel_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'entitlements' => $plan ? $this->catalog->entitlements($plan) : [],
        ];
    }

    public function transaction(PaymentTransaction $payment): array
    {
        return [
            'id' => $payment->id, 'user_id' => (int) $payment->user_id, 'subscription_id' => $payment->subscription_id,
            'provider' => $payment->provider, 'provider_transaction_ref' => $payment->provider_transaction_ref,
            'type' => $payment->type, 'amount_minor' => (int) $payment->amount_minor, 'currency' => $payment->currency,
            'status' => $payment->status, 'failure_code' => $payment->failure_code,
            'occurred_at' => $payment->occurred_at?->toIso8601String(),
        ];
    }

    public function refund(PaymentRefund $refund): array
    {
        return [
            'id' => $refund->id, 'payment_transaction_id' => $refund->payment_transaction_id,
            'user_id' => (int) $refund->user_id, 'amount_minor' => (int) $refund->amount_minor,
            'currency' => $refund->currency, 'status' => $refund->status, 'reason' => $refund->reason,
            'provider_ref' => $refund->provider_ref, 'provider_result' => $refund->provider_result,
            'requested_at' => $refund->requested_at?->toIso8601String(), 'decided_at' => $refund->decided_at?->toIso8601String(),
            'completed_at' => $refund->completed_at?->toIso8601String(),
        ];
    }
}
