<?php

declare(strict_types=1);

namespace App\Modules\Moments\Actions;

use App\Models\Moment;
use App\Models\User;
use App\Modules\Moments\Enums\MomentStatus;
use App\Modules\Moments\Services\MomentAccess;
use Illuminate\Database\Eloquent\Collection;

final class ListCircleMomentsAction
{
    public function __construct(private readonly MomentAccess $access) {}

    /**
     * @return Collection<int, Moment>
     */
    public function handle(User $user, string $circleId): Collection
    {
        $this->access->viewer($user, $circleId);

        return Moment::query()
            ->with(['author', 'mediaAsset'])
            ->withCount('views')
            ->where('circle_id', $circleId)
            ->where('status', MomentStatus::Active)
            ->whereNull('deleted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->limit(max(1, (int) config('orbit_moments.feed_limit', 100)))
            ->get();
    }
}
