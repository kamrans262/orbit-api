<?php

declare(strict_types=1);

namespace App\Modules\Activity\Listeners;

use App\Modules\Activity\Actions\RecordActivityEventAction;
use App\Modules\Activity\Enums\ActivityEventType;
use App\Modules\Sos\Events\SosEscalated;

final readonly class RecordSosEscalatedActivity
{
    public function __construct(private RecordActivityEventAction $record) {}

    public function handle(SosEscalated $event): void
    {
        $payload = $event->realtime['payload'] ?? [];
        $circleId = (string) ($payload['circle_id'] ?? '');
        $sosId = (string) ($payload['sos_id'] ?? '');
        $stage = isset($payload['stage']) ? (int) $payload['stage'] : 0;

        if ($circleId === '' || $sosId === '' || $stage < 1) {
            return;
        }

        $this->record->handle(
            ActivityEventType::SosEscalated,
            $circleId,
            null,
            'sos',
            $sosId,
            'sos.escalated:'.$sosId.':'.$stage,
            [
                'sos_id' => $sosId,
                'stage' => $stage,
            ],
        );
    }
}
