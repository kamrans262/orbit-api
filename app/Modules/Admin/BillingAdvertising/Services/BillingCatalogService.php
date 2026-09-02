<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Services;

use App\Models\BillingEntitlement;
use App\Models\BillingPlan;
use App\Models\BillingPlanEntitlement;
use App\Models\BillingPlanPrice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class BillingCatalogService
{
    public function syncDefaults(): void
    {
        DB::transaction(function (): void {
            $plans = [
                ['slug' => 'free', 'name' => 'Free', 'description' => 'Orbit Free plan.', 'rank' => 0],
                ['slug' => 'lite', 'name' => 'Orbit Lite', 'description' => 'Orbit Lite subscription plan.', 'rank' => 10],
                ['slug' => 'plus', 'name' => 'Orbit Plus', 'description' => 'Orbit Plus subscription plan.', 'rank' => 20],
            ];

            foreach ($plans as $definition) {
                BillingPlan::query()->updateOrCreate(
                    ['slug' => $definition['slug']],
                    [...$definition, 'status' => 'active'],
                );
            }

            $entitlements = [
                [
                    'slug' => 'ads.enabled',
                    'name' => 'Advertising enabled',
                    'value_type' => 'boolean',
                    'description' => 'Whether sponsored Orbit surfaces may be delivered.',
                ],
                [
                    'slug' => 'support.priority',
                    'name' => 'Priority support',
                    'value_type' => 'boolean',
                    'description' => 'Whether priority support entitlement is enabled.',
                ],
            ];

            foreach ($entitlements as $definition) {
                BillingEntitlement::query()->updateOrCreate(
                    ['slug' => $definition['slug']],
                    $definition,
                );
            }

            $ads = BillingEntitlement::query()->where('slug', 'ads.enabled')->firstOrFail();
            $priority = BillingEntitlement::query()->where('slug', 'support.priority')->firstOrFail();

            foreach (['free' => true, 'lite' => false, 'plus' => false] as $slug => $enabled) {
                $plan = BillingPlan::query()->where('slug', $slug)->firstOrFail();

                BillingPlanEntitlement::query()->updateOrCreate(
                    ['plan_id' => $plan->id, 'entitlement_id' => $ads->id],
                    ['value' => ['value' => $enabled]],
                );

                BillingPlanEntitlement::query()->updateOrCreate(
                    ['plan_id' => $plan->id, 'entitlement_id' => $priority->id],
                    ['value' => ['value' => $slug === 'plus']],
                );
            }
        });
    }

    public function activePrice(BillingPlan $plan, string $interval, string $currency): ?BillingPlanPrice
    {
        return BillingPlanPrice::query()
            ->where('plan_id', $plan->id)
            ->where('billing_interval', $interval)
            ->where('currency', strtoupper($currency))
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('starts_at')
            ->first();
    }

    public function entitlements(BillingPlan $plan): array
    {
        $values = [];

        $rows = BillingPlanEntitlement::query()
            ->where('plan_id', $plan->id)
            ->join(
                'billing_entitlements',
                'billing_entitlements.id',
                '=',
                'billing_plan_entitlements.entitlement_id',
            )
            ->get([
                'billing_entitlements.slug',
                'billing_plan_entitlements.value',
            ]);

        foreach ($rows as $row) {
            Arr::set($values, $row->slug, data_get($row->value, 'value'));
        }

        return $values;
    }
}
