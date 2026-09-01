<?php

declare(strict_types=1);

namespace App\Modules\Activity\Actions;

use App\Models\ActivityEvent;
use App\Modules\Activity\Enums\ActivityEventType;
use App\Modules\Activity\Events\ActivityItemCreated;
use App\Modules\Activity\Services\ActivityPresenter;
use Carbon\CarbonInterface;

final readonly class RecordActivityEventAction
{
    public function __construct(private ActivityPresenter $presenter) {}

    public function handle(
        ActivityEventType $type,
        string $circleId,
        ?int $actorUserId,
        string $sourceType,
        ?string $sourceId,
        string $eventKey,
        array $payload = [],
        ?CarbonInterface $occurredAt = null,
    ): ActivityEvent {
        $event = ActivityEvent::query()->createOrFirst(
            ['event_key' => $eventKey],
            [
                'circle_id' => $circleId,
                'actor_user_id' => $actorUserId,
                'event_type' => $type->value,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'payload' => $payload,
                'occurred_at' => $occurredAt ?? now(),
            ],
        );

        if ($event->wasRecentlyCreated) {
            event(new ActivityItemCreated([
                'channel' => 'orbit.circle.'.$event->circle_id,
                'event_name' => 'activity.created',
                'payload' => $this->presenter->present($event),
            ]));
        }

        return $event;
    }
}
