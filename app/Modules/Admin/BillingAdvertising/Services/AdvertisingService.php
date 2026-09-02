<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Services;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdEvent;
use App\Models\BillingPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AdvertisingService
{
    public function __construct(private readonly SubscriptionService $subscriptions, private readonly BillingCatalogService $catalog) {}

    public function eligible(User $user, string $placement, ?string $country, ?string $platform, int $limit): array
    {
        if ($this->hasActiveSos($user)) {
            return [];
        }
        $subscription = $this->subscriptions->current($user);
        $plan = BillingPlan::query()->find($subscription->plan_id);
        if ($plan === null || data_get($this->catalog->entitlements($plan), 'ads.enabled') !== true) {
            return [];
        }

        $campaigns = AdCampaign::query()->where('status', 'active')->where('placement', $placement)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('priority')->limit(100)->get();
        $items = [];
        foreach ($campaigns as $campaign) {
            if (! $this->targetMatches($campaign->targeting ?? [], $plan->slug, $country, $platform)) {
                continue;
            }
            if (AdEvent::query()->where('user_id', $user->id)->where('campaign_id', $campaign->id)->where('event_type', 'hide')->exists()) {
                continue;
            }
            if ($campaign->impression_cap_per_user !== null && AdEvent::query()->where('user_id', $user->id)->where('campaign_id', $campaign->id)->where('event_type', 'impression')->count() >= $campaign->impression_cap_per_user) {
                continue;
            }
            $creative = AdCreative::query()->where('campaign_id', $campaign->id)->where('status', 'active')->first();
            if ($creative === null) {
                continue;
            }
            $items[] = [
                'campaign_id' => $campaign->id, 'creative_id' => $creative->id, 'placement' => $campaign->placement,
                'title' => $creative->title, 'body' => $creative->body, 'media_ref' => $creative->media_ref,
                'deep_link' => $creative->deep_link, 'cta' => $creative->cta, 'sponsored' => true,
            ];
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    public function recordEvent(User $user, AdCampaign $campaign, array $data): AdEvent
    {
        if ($data['event_type'] !== 'hide') {
            $eligible = collect($this->eligible($user, $campaign->placement, $data['country'] ?? null, $data['platform'] ?? null, 100))->contains(fn (array $item): bool => $item['campaign_id'] === $campaign->id);
            if (! $eligible) {
                abort(404);
            }
        }
        if (! empty($data['client_event_id'])) {
            $existing = AdEvent::query()->where('client_event_id', $data['client_event_id'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return AdEvent::query()->create([
            'campaign_id' => $campaign->id, 'creative_id' => $data['creative_id'] ?? null, 'user_id' => $user->id,
            'event_type' => $data['event_type'], 'client_event_id' => $data['client_event_id'] ?? null,
            'context' => ['country' => $data['country'] ?? null, 'platform' => $data['platform'] ?? null], 'occurred_at' => now(),
        ]);
    }

    private function hasActiveSos(User $user): bool
    {
        return DB::table('sos_events')->where('user_id', $user->id)->whereNull('resolved_at')->exists();
    }

    private function targetMatches(array $targeting, string $plan, ?string $country, ?string $platform): bool
    {
        foreach ([['plans', $plan], ['countries', $country], ['platforms', $platform]] as [$key,$value]) {
            $allowed = $targeting[$key] ?? [];
            if (is_array($allowed) && $allowed !== [] && ($value === null || ! in_array($value, $allowed, true))) {
                return false;
            }
        }

        return true;
    }
}
