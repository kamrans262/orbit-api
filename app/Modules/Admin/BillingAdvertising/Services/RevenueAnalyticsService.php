<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Services;

use App\Models\BillingPlan;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\UserSubscription;
use Carbon\CarbonImmutable;

final class RevenueAnalyticsService
{
    public function summary(?string $from, ?string $to): array
    {
        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
        $end = $to ? CarbonImmutable::parse($to)->endOfDay() : now()->endOfDay();
        $payments = PaymentTransaction::query()->whereBetween('occurred_at', [$start, $end]);
        $gross = (int) (clone $payments)->whereIn('status', ['succeeded', 'partially_refunded', 'refunded'])->sum('amount_minor');
        $failed = (int) (clone $payments)->where('status', 'failed')->count();
        $refunds = (int) PaymentRefund::query()->where('status', 'succeeded')->whereBetween('completed_at', [$start, $end])->sum('amount_minor');

        $active = UserSubscription::query()->whereIn('status', ['active', 'cancel_pending'])->get();
        $mrr = 0;
        foreach ($active as $subscription) {
            $amount = (int) $subscription->price_amount_minor;
            $mrr += $subscription->billing_interval === 'annual' ? (int) round($amount / 12) : $amount;
        }
        $planCounts = BillingPlan::query()->orderBy('rank')->get()->mapWithKeys(fn (BillingPlan $plan): array => [
            $plan->slug => UserSubscription::query()->where('plan_id', $plan->id)->whereIn('status', ['active', 'cancel_pending'])->count(),
        ])->all();

        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'gross_revenue_minor' => $gross, 'refunds_minor' => $refunds, 'net_revenue_minor' => max(0, $gross - $refunds),
            'mrr_minor' => $mrr, 'arr_minor' => $mrr * 12, 'failed_payments' => $failed,
            'active_subscriptions' => $active->count(), 'cancel_pending' => $active->where('status', 'cancel_pending')->count(),
            'plan_counts' => $planCounts,
        ];
    }
}
