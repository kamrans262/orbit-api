<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Models\ActivityEvent;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Moment;
use App\Models\PresenceState;
use App\Models\SosEvent;
use App\Models\User;
use App\Modules\Activity\Services\ActivityPresenter;
use App\Modules\Moments\Enums\MomentStatus;
use App\Modules\Moments\Services\MomentPresenter;
use App\Modules\Presence\Services\PresencePresenter;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class MemberRecentService
{
    public function __construct(
        private PresencePresenter $presence,
        private MomentPresenter $moments,
        private ActivityPresenter $activity,
    ) {}

    public function forUser(User $viewer, User $target): array
    {
        if ((int) $viewer->id === (int) $target->id) {
            $sharedCircleIds = DB::table('circle_members')->where('user_id', $viewer->id)->pluck('circle_id');
        } else {
            $viewerCircleIds = DB::table('circle_members')->where('user_id', $viewer->id)->pluck('circle_id');
            $sharedCircleIds = DB::table('circle_members')->where('user_id', $target->id)->whereIn('circle_id', $viewerCircleIds)->pluck('circle_id');
        }

        $sharedCircleIds = Circle::query()->whereIn('id', $sharedCircleIds)->available()->pluck('id');
        if ($sharedCircleIds->isEmpty()) {
            throw new NotFoundHttpException('User is unavailable.');
        }

        $viewableMomentCircleIds = CircleMember::query()
            ->where('user_id', $viewer->id)
            ->whereIn('circle_id', $sharedCircleIds)
            ->where('can_view_moments', true)
            ->pluck('circle_id');

        $presenceState = PresenceState::query()->where('user_id', $target->id)->first();
        $memberships = CircleMember::query()->with(['circle', 'user'])->where('user_id', $target->id)->whereIn('circle_id', $sharedCircleIds)->get();

        $moments = Moment::query()
            ->where('author_user_id', $target->id)
            ->whereIn('circle_id', $viewableMomentCircleIds)
            ->where('status', MomentStatus::Active)
            ->whereNull('deleted_at')
            ->where('expires_at', '>', now())
            ->with(['author', 'mediaAsset'])->withCount('views')->latest()->limit(6)->get()
            ->map(fn (Moment $moment): array => $this->moments->make($moment, $viewer))->values()->all();

        $activity = ActivityEvent::query()
            ->where('actor_user_id', $target->id)
            ->whereIn('circle_id', $sharedCircleIds)
            ->whereNull('removed_at')
            ->whereNotExists(function ($query) use ($viewer): void {
                $query->selectRaw('1')
                    ->from('activity_hidden_events')
                    ->whereColumn('activity_hidden_events.activity_event_id', 'activity_events.id')
                    ->where('activity_hidden_events.user_id', $viewer->id);
            })
            ->latest('occurred_at')->limit(10)->get()
            ->map(fn (ActivityEvent $event): array => $this->activity->present($event))->values()->all();

        $sosActive = SosEvent::query()->where('user_id', $target->id)->whereIn('circle_id', $sharedCircleIds)->where('status', 'active')->exists();

        return [
            'user' => ['id' => $target->id, 'name' => $target->name],
            'presence_by_circle' => $memberships->map(fn (CircleMember $membership): array => [
                'circle' => ['id' => $membership->circle_id, 'name' => $membership->circle?->name],
                'presence' => $this->presence->forCircle($membership, $presenceState),
            ])->values()->all(),
            'recent_moments' => $moments,
            'recent_activity' => $activity,
            'sos_active' => $sosActive,
        ];
    }
}
