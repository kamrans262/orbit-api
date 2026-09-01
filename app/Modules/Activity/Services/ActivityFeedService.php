<?php

declare(strict_types=1);

namespace App\Modules\Activity\Services;

use App\Models\ActivityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class ActivityFeedService
{
    public function __construct(private ActivityPresenter $presenter) {}

    public function feed(User $user, int $limit = 20): array
    {
        $paginator = $this->visibleQuery($user)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate($limit);

        return [
            'data' => collect($paginator->items())
                ->map(fn (ActivityEvent $event): array => $this->presenter->present($event))
                ->values()
                ->all(),
            'meta' => [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function dashboardPreview(User $user): array
    {
        return $this->visibleQuery($user)
            ->where('occurred_at', '>=', now()->subHours(24))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn (ActivityEvent $event): array => $this->presenter->present($event))
            ->values()
            ->all();
    }

    private function visibleQuery(User $user): Builder
    {
        $circleIds = DB::table('circle_members')
            ->where('user_id', $user->getKey())
            ->pluck('circle_id');

        return ActivityEvent::query()
            ->whereIn('circle_id', $circleIds)
            ->whereNull('removed_at')
            ->whereNotExists(function ($query) use ($user): void {
                $query->selectRaw('1')
                    ->from('activity_hidden_events')
                    ->whereColumn('activity_hidden_events.activity_event_id', 'activity_events.id')
                    ->where('activity_hidden_events.user_id', $user->getKey());
            });
    }
}
