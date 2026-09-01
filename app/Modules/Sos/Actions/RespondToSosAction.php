<?php

declare(strict_types=1);

namespace App\Modules\Sos\Actions;

use App\Models\SosEvent;
use App\Models\SosResponder;
use App\Models\User;
use App\Modules\Sos\Enums\SosResponderStatus;
use App\Modules\Sos\Enums\SosStatus;
use App\Modules\Sos\Events\SosResponderEngaged;
use App\Modules\Sos\Exceptions\SosException;
use App\Modules\Sos\Services\SosAccessService;
use Illuminate\Support\Facades\DB;

final readonly class RespondToSosAction
{
    public function __construct(private SosAccessService $access) {}

    public function handle(User $user, SosEvent $event, string $status): SosResponder
    {
        $this->access->assertEventMember($user, $event);

        if ($event->status !== SosStatus::Active->value) {
            throw SosException::notActive();
        }

        if ((int) $event->user_id === (int) $user->getKey()) {
            throw SosException::originatorCannotRespond();
        }

        $dispatchEngaged = false;

        $responder = DB::transaction(function () use ($user, $event, $status, &$dispatchEngaged): SosResponder {
            $lockedEvent = SosEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($lockedEvent->status !== SosStatus::Active->value) {
                throw SosException::notActive();
            }

            $responder = SosResponder::query()->firstOrNew([
                'sos_event_id' => $lockedEvent->id,
                'user_id' => $user->getKey(),
            ]);

            if ($responder->status === $status && $responder->responded_at !== null) {
                return $responder;
            }

            $now = now();
            $responder->status = $status;
            $responder->responded_at = $now;
            $responder->engaged_at = $status === SosResponderStatus::Engaged->value ? ($responder->engaged_at ?? $now) : null;
            $responder->save();

            $dispatchEngaged = $status === SosResponderStatus::Engaged->value;

            return $responder;
        });

        if ($dispatchEngaged) {
            event(new SosResponderEngaged([
                'channel' => 'orbit.sos.'.$event->id,
                'event_name' => 'sos.responder.engaged',
                'payload' => [
                    'sos_id' => $event->id,
                    'responder_user_id' => $user->getKey(),
                    'engaged_at' => $responder->engaged_at?->toIso8601String(),
                ],
            ]));
        }

        return $responder;
    }
}
