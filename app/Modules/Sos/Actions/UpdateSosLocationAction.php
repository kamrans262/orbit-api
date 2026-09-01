<?php

declare(strict_types=1);

namespace App\Modules\Sos\Actions;

use App\Models\SosEvent;
use App\Models\User;
use App\Modules\Sos\Enums\SosResponderStatus;
use App\Modules\Sos\Enums\SosStatus;
use App\Modules\Sos\Events\SosLocationUpdated;
use App\Modules\Sos\Exceptions\SosException;
use App\Modules\Sos\Services\SosAccessService;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSosLocationAction
{
    public function __construct(private SosAccessService $access) {}

    public function handle(User $user, SosEvent $event, array $data): void
    {
        $this->access->assertEventMember($user, $event);

        if ($event->status !== SosStatus::Active->value) {
            throw SosException::notActive();
        }

        $actorRole = DB::transaction(function () use ($user, $event, $data): string {
            $now = now();
            $lockedEvent = SosEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($lockedEvent->status !== SosStatus::Active->value) {
                throw SosException::notActive();
            }

            if ((int) $lockedEvent->user_id === (int) $user->getKey()) {
                $lockedEvent->forceFill([
                    'last_latitude' => $data['latitude'],
                    'last_longitude' => $data['longitude'],
                    'last_location_accuracy_m' => $data['accuracy_m'] ?? null,
                    'last_location_at' => $now,
                ])->save();

                return 'originator';
            }

            $responder = $lockedEvent->responders()
                ->where('user_id', $user->getKey())
                ->where('status', SosResponderStatus::Engaged->value)
                ->lockForUpdate()
                ->first();

            if (! $responder) {
                throw SosException::locationForbidden();
            }

            $responder->forceFill([
                'last_latitude' => $data['latitude'],
                'last_longitude' => $data['longitude'],
                'last_location_accuracy_m' => $data['accuracy_m'] ?? null,
                'last_location_at' => $now,
            ])->save();

            return 'responder';
        });

        event(new SosLocationUpdated([
            'channel' => 'orbit.sos.'.$event->id,
            'event_name' => 'sos.location.updated',
            'payload' => [
                'sos_id' => $event->id,
                'user_id' => $user->getKey(),
                'role' => $actorRole,
                'latitude' => (float) $data['latitude'],
                'longitude' => (float) $data['longitude'],
                'accuracy_m' => isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
                'recorded_at' => now()->toIso8601String(),
            ],
        ]));
    }
}
