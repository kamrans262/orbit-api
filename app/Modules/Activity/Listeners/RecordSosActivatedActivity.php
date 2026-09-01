<?php

declare(strict_types=1);

namespace App\Modules\Activity\Listeners;

use App\Modules\Activity\Actions\RecordActivityEventAction;
use App\Modules\Activity\Enums\ActivityEventType;
use App\Modules\Sos\Events\SosActivated;

final readonly class RecordSosActivatedActivity
{
    public function __construct(private RecordActivityEventAction $record) {}

    public function handle(SosActivated $event): void
    {
        $payload = $event->realtime['payload'] ?? [];
        $circleId = (string) ($payload['circle_id'] ?? '');
        $sosId = (string) ($payload['sos_id'] ?? '');

        if ($circleId === '' || $sosId === '') {
            return;
        }

        $originatorUserId = $payload['originator_user_id'] ?? null;

        $this->record->handle(
            ActivityEventType::SosActivated,
            $circleId,
            is_numeric($originatorUserId) ? (int) $originatorUserId : null,
            'sos',
            $sosId,
            'sos.activated:'.$sosId,
            ['sos_id' => $sosId],
        );
    }
}
