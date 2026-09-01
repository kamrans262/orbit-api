<?php

declare(strict_types=1);

namespace App\Modules\Moments\Actions;

use App\Models\Moment;
use App\Models\MomentView;
use App\Models\User;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Moments\Enums\MomentStatus;
use App\Modules\Moments\Events\MomentViewed;
use App\Modules\Moments\Exceptions\MomentException;
use App\Modules\Moments\Services\MomentAccess;

final class RecordMomentViewAction
{
    public function __construct(private readonly MomentAccess $access) {}

    /**
     * @return array{recorded:bool,anonymous:bool}
     */
    public function handle(User $user, string $momentId): array
    {
        $moment = Moment::query()->whereKey($momentId)->first();

        if ($moment === null || $moment->status === MomentStatus::Deleted || $moment->deleted_at !== null) {
            throw MomentException::notFound();
        }

        $membership = $this->access->viewer($user, $moment->circle_id);

        if ($moment->status === MomentStatus::Expired || $moment->expires_at->isPast()) {
            if ($moment->status === MomentStatus::Active) {
                $moment->forceFill(['status' => MomentStatus::Expired])->save();
            }

            throw MomentException::expired();
        }

        if ($moment->author_user_id === $user->id) {
            return ['recorded' => false, 'anonymous' => false];
        }

        $anonymous = (bool) ($user->global_ghost_mode ?? false)
            || $membership->location_mode === LocationMode::Ghost;

        $view = MomentView::query()->firstOrCreate(
            [
                'moment_id' => $moment->id,
                'viewer_user_id' => $user->id,
            ],
            [
                'is_anonymous' => $anonymous,
                'viewed_at' => now(),
            ],
        );

        if ($view->wasRecentlyCreated) {
            MomentViewed::dispatch(
                moment: $moment,
                viewerUserId: $anonymous ? null : $user->id,
                anonymous: $anonymous,
                viewedAt: $view->viewed_at->toIso8601String(),
            );
        }

        return [
            'recorded' => $view->wasRecentlyCreated,
            'anonymous' => $view->is_anonymous,
        ];
    }
}
