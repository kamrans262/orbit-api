<?php

declare(strict_types=1);

namespace App\Modules\Activity\Listeners;

use App\Modules\Activity\Actions\RecordActivityEventAction;
use App\Modules\Activity\Enums\ActivityEventType;
use App\Modules\Sos\Events\SosResolved;

final readonly class RecordSosResolvedActivity
{
    public function __construct(private RecordActivityEventAction $record) {}

    public function handle(SosResolved $event): void
    {
        $payload = $event->realtime['payload'] ?? [];
        $circleId = (string) ($payload['circle_id'] ?? '');
        $sosId = (string) ($payload['sos_id'] ?? '');

        if ($circleId === '' || $sosId === '') {
            return;
        }

        $this->record->handle(
            ActivityEventType::SosResolved,
            $circleId,
            null,
            'sos',
            $sosId,
            'sos.resolved:'.$sosId,
            ['sos_id' => $sosId],
        );
    }
}
