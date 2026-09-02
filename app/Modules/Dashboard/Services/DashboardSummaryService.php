<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Models\Circle;
use App\Models\Moment;
use App\Models\OrbitNotification;
use App\Models\Ping;
use App\Models\PresenceState;
use App\Models\SosEvent;
use App\Models\User;
use App\Models\UserSubscription;
use App\Modules\Activity\Services\ActivityFeedService;
use App\Modules\Moments\Enums\MomentStatus;
use App\Modules\Moments\Services\MomentPresenter;
use App\Modules\Ping\Enums\PingStatus;
use App\Modules\Presence\Services\PresencePresenter;
use Illuminate\Support\Facades\DB;

final readonly class DashboardSummaryService
{
    public function __construct(
        private ActivityFeedService $activity,
        private PresencePresenter $presence,
        private MomentPresenter $moments,
    ) {}

    public function forUser(User $user): array
    {
        $circleIds = DB::table('circle_members')->where('user_id', $user->id)->pluck('circle_id');
        $availableCircleIds = Circle::query()->whereIn('id', $circleIds)->available()->pluck('id');
        $viewableMomentCircleIds = DB::table('circle_members')
            ->where('user_id', $user->id)
            ->whereIn('circle_id', $availableCircleIds)
            ->where('can_view_moments', true)
            ->pluck('circle_id');
        $circles = Circle::query()->whereIn('id', $availableCircleIds)->withCount('memberships')->latest()->limit(6)->get();
        $presence = PresenceState::query()->where('user_id', $user->id)->first();

        $recentMoments = Moment::query()
            ->whereIn('circle_id', $viewableMomentCircleIds)
            ->where('status', MomentStatus::Active)
            ->whereNull('deleted_at')
            ->where('expires_at', '>', now())
            ->with(['author', 'mediaAsset'])
            ->withCount('views')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Moment $moment): array => $this->moments->make($moment, $user))
            ->values()->all();

        $activeSos = SosEvent::query()->where('user_id', $user->id)->where('status', 'active')->latest('activated_at')->first();
        $subscription = UserSubscription::query()->where('user_id', $user->id)->whereIn('status', ['active', 'cancel_pending'])->latest('started_at')->first();
        $plan = $subscription ? DB::table('billing_plans')->where('id', $subscription->plan_id)->first(['slug', 'name']) : null;

        return [
            'circles' => [
                'count' => $availableCircleIds->count(),
                'items' => $circles->map(fn (Circle $circle): array => [
                    'id' => $circle->id,
                    'name' => $circle->name,
                    'type' => $circle->type->value,
                    'member_count' => (int) $circle->memberships_count,
                ])->all(),
            ],
            'presence' => $this->presence->forOwner($user, $presence),
            'moments' => $recentMoments,
            'activity' => $this->activity->dashboardPreview($user),
            'pending_pings' => Ping::query()
                ->whereHas('recipientMembership', fn ($q) => $q->where('user_id', $user->id))
                ->where('status', PingStatus::Pending)
                ->where('expires_at', '>', now())
                ->count(),
            'unread_notifications' => OrbitNotification::query()->where('user_id', $user->id)->whereNull('read_at')->where('in_app_visible', true)->count(),
            'active_sos' => $activeSos ? [
                'id' => $activeSos->id,
                'status' => $activeSos->status,
                'escalation_stage' => (int) $activeSos->escalation_stage,
                'activated_at' => $activeSos->activated_at?->toIso8601String(),
            ] : null,
            'subscription' => [
                'status' => $subscription?->status ?? 'free',
                'plan' => $plan ? ['slug' => $plan->slug, 'name' => $plan->name] : ['slug' => 'free', 'name' => 'Free'],
            ],
        ];
    }
}
