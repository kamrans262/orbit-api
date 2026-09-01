<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Activity\Events\ActivityItemCreated;
use App\Modules\Activity\Events\ActivityItemRemoved;
use App\Modules\Activity\Http\Middleware\TrackCircleMembershipChanges;
use App\Modules\Activity\Listeners\BroadcastActivityItemCreated;
use App\Modules\Activity\Listeners\BroadcastActivityItemRemoved;
use App\Modules\Activity\Listeners\RecordMomentPublishedActivity;
use App\Modules\Activity\Listeners\RecordSosActivatedActivity;
use App\Modules\Activity\Listeners\RecordSosEscalatedActivity;
use App\Modules\Activity\Listeners\RecordSosResolvedActivity;
use App\Modules\Activity\Listeners\RemoveMomentActivity;
use App\Modules\Moments\Events\MomentDeleted;
use App\Modules\Moments\Events\MomentPublished;
use App\Modules\Sos\Events\SosActivated;
use App\Modules\Sos\Events\SosEscalated;
use App\Modules\Sos\Events\SosResolved;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ActivityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ActivityItemCreated::class, BroadcastActivityItemCreated::class);
        Event::listen(ActivityItemRemoved::class, BroadcastActivityItemRemoved::class);

        if (class_exists(MomentPublished::class)) {
            Event::listen(MomentPublished::class, RecordMomentPublishedActivity::class);
        }

        if (class_exists(MomentDeleted::class)) {
            Event::listen(MomentDeleted::class, RemoveMomentActivity::class);
        }

        if (class_exists(SosActivated::class)) {
            Event::listen(SosActivated::class, RecordSosActivatedActivity::class);
        }

        if (class_exists(SosEscalated::class)) {
            Event::listen(SosEscalated::class, RecordSosEscalatedActivity::class);
        }

        if (class_exists(SosResolved::class)) {
            Event::listen(SosResolved::class, RecordSosResolvedActivity::class);
        }

        Event::listen(RouteMatched::class, function (RouteMatched $event): void {
            $route = $event->route;
            $uri = ltrim($route->uri(), '/');
            $isCircleRoute = str_starts_with($uri, 'api/v1/circles')
                || str_starts_with($uri, 'v1/circles');
            $isMutation = array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [];

            if ($isCircleRoute && $isMutation) {
                $route->middleware(TrackCircleMembershipChanges::class);
            }
        });
    }
}
