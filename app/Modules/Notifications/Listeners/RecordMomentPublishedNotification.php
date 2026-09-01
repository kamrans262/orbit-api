<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Models\Moment;
use App\Modules\Moments\Events\MomentPublished;
use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class RecordMomentPublishedNotification
{
    public function __construct(private RouteNotificationAction $route) {}

    public function handle(MomentPublished $event): void
    {
        $moment = $event->moment;
        if (! $moment instanceof Moment) {
            return;
        }

        $circleId = (string) $moment->getAttribute('circle_id');
        $momentId = (string) $moment->getKey();
        $authorId = $moment->getAttribute('author_user_id') ?? $moment->getAttribute('sender_user_id') ?? $moment->getAttribute('user_id');

        if ($circleId === '' || $momentId === '' || ! is_numeric($authorId)) {
            return;
        }

        $query = DB::table('circle_members')->where('circle_id', $circleId)->where('user_id', '!=', (int) $authorId);
        if (Schema::hasColumn('circle_members', 'can_view_moments')) {
            $query->where('can_view_moments', true);
        }

        foreach ($query->pluck('user_id') as $userId) {
            $this->route->handle(
                (int) $userId,
                'moment.published',
                'moment:'.$momentId.':user:'.$userId,
                [
                    'moment_id' => $momentId,
                    'circle_id' => $circleId,
                    'author_user_id' => (int) $authorId,
                    'media_type' => $moment->getAttribute('media_type') ?? $moment->getAttribute('type'),
                    'deep_link' => 'orbit://moments/'.$momentId,
                ],
                NotificationPriority::Normal,
                $circleId,
                'orbit://moments/'.$momentId,
            );
        }
    }
}
