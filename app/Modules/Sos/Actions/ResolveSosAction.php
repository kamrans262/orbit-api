<?php

declare(strict_types=1);

namespace App\Modules\Sos\Actions;

use App\Models\SosEvent;
use App\Models\User;
use App\Modules\Sos\Enums\SosStatus;
use App\Modules\Sos\Events\SosResolved;
use App\Modules\Sos\Exceptions\SosException;
use App\Modules\Sos\Services\SosAccessService;
use Illuminate\Support\Facades\DB;

final readonly class ResolveSosAction
{
    public function __construct(private SosAccessService $access) {}

    public function handle(User $user, SosEvent $event, ?string $reason): SosEvent
    {
        $this->access->assertEventMember($user, $event);

        if ((int) $event->user_id !== (int) $user->getKey()) {
            throw SosException::resolveForbidden();
        }

        if ($event->status === SosStatus::Resolved->value) {
            return $event;
        }

        $resolved = DB::transaction(function () use ($event, $reason): SosEvent {
            $locked = SosEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->status === SosStatus::Resolved->value) {
                return $locked;
            }

            $locked->forceFill([
                'status' => SosStatus::Resolved->value,
                'resolved_at' => now(),
                'resolution_reason' => $reason,
            ])->save();

            return $locked;
        });

        event(new SosResolved([
            'channel' => 'orbit.circle.'.$resolved->circle_id,
            'event_name' => 'sos.resolved',
            'payload' => [
                'sos_id' => $resolved->id,
                'circle_id' => $resolved->circle_id,
                'resolved_at' => $resolved->resolved_at?->toIso8601String(),
                'resolution_reason' => $resolved->resolution_reason,
            ],
        ]));

        return $resolved;
    }
}
