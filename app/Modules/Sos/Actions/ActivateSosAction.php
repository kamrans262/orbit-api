<?php

declare(strict_types=1);

namespace App\Modules\Sos\Actions;

use App\Models\SosEvent;
use App\Models\SosResponder;
use App\Models\User;
use App\Modules\Sos\Enums\SosResponderStatus;
use App\Modules\Sos\Enums\SosStatus;
use App\Modules\Sos\Events\SosActivated;
use App\Modules\Sos\Exceptions\SosException;
use App\Modules\Sos\Services\SosAccessService;
use App\Modules\Sos\Services\SosNotificationOutboxService;
use App\Modules\Sos\Values\ActivateSosResult;
use Illuminate\Support\Facades\DB;

final readonly class ActivateSosAction
{
    public function __construct(
        private SosAccessService $access,
        private SosNotificationOutboxService $notifications,
    ) {}

    public function handle(User $user, array $data): ActivateSosResult
    {
        $this->access->assertCircleMember($user, $data['circle_id']);

        $existing = SosEvent::query()->find($data['id']);

        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->getKey() || $existing->circle_id !== $data['circle_id']) {
                throw SosException::idConflict();
            }

            return new ActivateSosResult($existing->load('responders'), false);
        }

        $recentActivations = SosEvent::query()
            ->where('user_id', $user->getKey())
            ->where('activated_at', '>=', now()->subMinutes(60))
            ->count();

        if ($recentActivations >= 3) {
            throw SosException::tooManyActivations();
        }

        $event = DB::transaction(function () use ($user, $data): SosEvent {
            $now = now();

            $event = SosEvent::query()->create([
                'id' => $data['id'],
                'user_id' => $user->getKey(),
                'circle_id' => $data['circle_id'],
                'status' => SosStatus::Active->value,
                'escalation_stage' => 0,
                'activated_at' => $now,
                'recording_ref' => $data['recording_ref'] ?? null,
                'recording_expires_at' => isset($data['recording_ref']) ? $now->copy()->addDays(90) : null,
                'last_latitude' => $data['latitude'] ?? null,
                'last_longitude' => $data['longitude'] ?? null,
                'last_location_accuracy_m' => $data['location_accuracy_m'] ?? null,
                'last_location_at' => isset($data['latitude']) ? $now : null,
            ]);

            $memberIds = DB::table('circle_members')
                ->where('circle_id', $event->circle_id)
                ->where('user_id', '!=', $user->getKey())
                ->pluck('user_id');

            foreach ($memberIds as $memberId) {
                SosResponder::query()->create([
                    'sos_event_id' => $event->id,
                    'user_id' => (int) $memberId,
                    'status' => SosResponderStatus::Pending->value,
                ]);
            }

            $this->notifications->queueActivation($event);

            return $event->load('responders');
        });

        event(new SosActivated([
            'channel' => 'orbit.circle.'.$event->circle_id,
            'event_name' => 'sos.activated',
            'payload' => [
                'sos_id' => $event->id,
                'circle_id' => $event->circle_id,
                'originator_user_id' => $event->user_id,
                'activated_at' => $event->activated_at?->toIso8601String(),
                'latitude' => $event->last_latitude,
                'longitude' => $event->last_longitude,
            ],
        ]));

        return new ActivateSosResult($event, true);
    }
}
