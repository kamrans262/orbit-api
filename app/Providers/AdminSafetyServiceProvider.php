<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AdminSosIncidentControl;
use App\Models\Circle;
use App\Models\SosEvent;
use App\Modules\Admin\Safety\Listeners\BroadcastAdminSosLifecycleUpdate;
use App\Modules\Sos\Events\SosActivated;
use App\Modules\Sos\Events\SosEscalated;
use App\Modules\Sos\Events\SosLocationUpdated;
use App\Modules\Sos\Events\SosResolved;
use App\Modules\Sos\Events\SosResponderEngaged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AdminSafetyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        SosEvent::resolveRelationUsing(
            'circle',
            fn (SosEvent $incident) => $incident->belongsTo(Circle::class, 'circle_id'),
        );

        SosEvent::resolveRelationUsing(
            'adminSafetyControl',
            fn (SosEvent $incident) => $incident->hasOne(AdminSosIncidentControl::class, 'sos_event_id'),
        );

        foreach ([
            SosActivated::class,
            SosEscalated::class,
            SosLocationUpdated::class,
            SosResolved::class,
            SosResponderEngaged::class,
        ] as $eventClass) {
            Event::listen($eventClass, BroadcastAdminSosLifecycleUpdate::class);
        }
    }
}
