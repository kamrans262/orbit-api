<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Listeners;

use App\Models\SosEvent;
use App\Modules\Admin\Safety\Services\AdminSosRealtimeService;

final readonly class BroadcastAdminSosLifecycleUpdate
{
    public function __construct(private AdminSosRealtimeService $realtime) {}

    public function handle(object $event): void
    {
        $realtime = property_exists($event, 'realtime') && is_array($event->realtime)
            ? $event->realtime
            : [];

        $payload = isset($realtime['payload']) && is_array($realtime['payload'])
            ? $realtime['payload']
            : [];

        $sosId = $payload['sos_id'] ?? null;
        if (! is_string($sosId) || $sosId === '') {
            return;
        }

        $incident = SosEvent::query()->find($sosId);
        if ($incident === null) {
            return;
        }

        $changeType = isset($realtime['event_name']) && is_string($realtime['event_name'])
            ? $realtime['event_name']
            : 'sos.updated';

        $this->realtime->broadcast($incident, $changeType);
    }
}
