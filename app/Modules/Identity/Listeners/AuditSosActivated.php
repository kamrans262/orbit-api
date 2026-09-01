<?php

declare(strict_types=1);

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Services\AuditLogger;

final class AuditSosActivated
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(object $event): void
    {
        $payload = is_array($event->realtime ?? null)
            ? (array) data_get($event->realtime, 'payload', [])
            : [];

        $userId = data_get($payload, 'originator_user_id')
            ?? data_get($payload, 'user_id');
        $sosId = data_get($payload, 'sos_id');
        $circleId = data_get($payload, 'circle_id');

        if (! is_numeric($userId)) {
            return;
        }

        $this->audit->write(
            'identity.sos.activated',
            (int) $userId,
            targetType: 'sos',
            targetId: is_scalar($sosId) ? (string) $sosId : null,
            metadata: ['circle_id' => is_scalar($circleId) ? (string) $circleId : null],
        );
    }
}
