<?php

declare(strict_types=1);

namespace App\Modules\Sos\Services;

use App\Models\SosEvent;
use App\Models\User;
use App\Modules\Sos\Enums\SosResponderStatus;

final class SosPresenter
{
    public function present(SosEvent $event, User $viewer): array
    {
        $event->loadMissing('responders');

        $viewerIsOriginator = (int) $event->user_id === (int) $viewer->getKey();
        $viewerIsEngaged = $event->responders->contains(
            fn ($responder): bool => (int) $responder->user_id === (int) $viewer->getKey()
                && $responder->status === SosResponderStatus::Engaged->value,
        );
        $maySeeResponderLocation = $viewerIsOriginator || $viewerIsEngaged;

        return [
            'id' => $event->id,
            'circle_id' => $event->circle_id,
            'originator_user_id' => $event->user_id,
            'status' => $event->status,
            'escalation_stage' => $event->escalation_stage,
            'activated_at' => $event->activated_at?->toIso8601String(),
            'resolved_at' => $event->resolved_at?->toIso8601String(),
            'resolution_reason' => $event->resolution_reason,
            'recording_ref' => $maySeeResponderLocation ? $event->recording_ref : null,
            'recording_expires_at' => $maySeeResponderLocation ? $event->recording_expires_at?->toIso8601String() : null,
            'originator_location' => $this->locationPayload(
                $event->last_latitude,
                $event->last_longitude,
                $event->last_location_accuracy_m,
                $event->last_location_at?->toIso8601String(),
            ),
            'responders' => $event->responders
                ->sortBy('user_id')
                ->values()
                ->map(fn ($responder): array => [
                    'user_id' => $responder->user_id,
                    'status' => $responder->status,
                    'engaged_at' => $responder->engaged_at?->toIso8601String(),
                    'responded_at' => $responder->responded_at?->toIso8601String(),
                    'location' => $maySeeResponderLocation && $responder->status === SosResponderStatus::Engaged->value
                        ? $this->locationPayload(
                            $responder->last_latitude,
                            $responder->last_longitude,
                            $responder->last_location_accuracy_m,
                            $responder->last_location_at?->toIso8601String(),
                        )
                        : null,
                ])
                ->all(),
        ];
    }

    private function locationPayload(?float $latitude, ?float $longitude, ?float $accuracy, ?string $recordedAt): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_m' => $accuracy,
            'recorded_at' => $recordedAt,
        ];
    }
}
