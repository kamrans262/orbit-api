<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Models\SosNotificationOutbox;
use App\Modules\Notifications\Enums\NotificationPriority;
use Illuminate\Support\Facades\Schema;

final readonly class ImportSosNotificationOutboxAction
{
    public function __construct(private RouteNotificationAction $route) {}

    public function handle(?string $sosEventId = null): int
    {
        if (! Schema::hasTable('sos_notification_outbox')) {
            return 0;
        }

        $query = SosNotificationOutbox::query()
            ->where('channel', 'push')
            ->where('status', 'pending')
            ->where('available_at', '<=', now());

        if ($sosEventId !== null) {
            $query->where('sos_event_id', $sosEventId);
        }

        $processed = 0;

        $query->orderBy('available_at')->orderBy('id')->chunk(100, function ($rows) use (&$processed): void {
            foreach ($rows as $row) {
                $payload = is_array($row->payload) ? $row->payload : [];
                $circleId = isset($payload['circle_id']) ? (string) $payload['circle_id'] : null;

                $this->route->handle(
                    (int) $row->target_user_id,
                    (string) $row->kind,
                    'sos-outbox:'.$row->id,
                    $payload,
                    NotificationPriority::Highest,
                    $circleId,
                    isset($payload['deep_link']) ? (string) $payload['deep_link'] : null,
                );

                $row->status = 'accepted';
                $row->delivered_at = now();
                $row->attempts = (int) $row->attempts + 1;
                $row->save();
                $processed++;
            }
        });

        return $processed;
    }
}
