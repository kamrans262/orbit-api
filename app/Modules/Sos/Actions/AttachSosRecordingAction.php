<?php

declare(strict_types=1);

namespace App\Modules\Sos\Actions;

use App\Models\SosEvent;
use App\Models\User;
use App\Modules\Sos\Exceptions\SosException;
use App\Modules\Sos\Services\SosAccessService;
use Illuminate\Support\Facades\DB;

final readonly class AttachSosRecordingAction
{
    public function __construct(private SosAccessService $access) {}

    public function handle(User $user, SosEvent $event, string $recordingRef): SosEvent
    {
        $this->access->assertEventMember($user, $event);

        if ((int) $event->user_id !== (int) $user->getKey()) {
            throw SosException::recordingForbidden();
        }

        return DB::transaction(function () use ($event, $recordingRef): SosEvent {
            $locked = SosEvent::query()->lockForUpdate()->findOrFail($event->id);
            $locked->forceFill([
                'recording_ref' => $recordingRef,
                'recording_expires_at' => now()->addDays(90),
            ])->save();

            return $locked;
        });
    }
}
