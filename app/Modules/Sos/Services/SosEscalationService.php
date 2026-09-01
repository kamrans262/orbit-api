<?php

declare(strict_types=1);

namespace App\Modules\Sos\Services;

use App\Models\SosEscalation;
use App\Models\SosEvent;
use App\Modules\Sos\Enums\SosResponderStatus;
use App\Modules\Sos\Enums\SosStatus;
use App\Modules\Sos\Events\SosEscalated;
use Illuminate\Support\Facades\DB;

final readonly class SosEscalationService
{
    public function __construct(private SosNotificationOutboxService $notifications) {}

    public function processDue(): int
    {
        $processed = 0;

        SosEvent::query()
            ->where('status', SosStatus::Active->value)
            ->where('activated_at', '<=', now()->subSeconds(60))
            ->chunkById(100, function ($events) use (&$processed): void {
                foreach ($events as $event) {
                    $processed += $this->processEvent($event);
                }
            }, 'id');

        return $processed;
    }

    public function processEvent(SosEvent $event): int
    {
        return DB::transaction(function () use ($event): int {
            $locked = SosEvent::query()->lockForUpdate()->find($event->id);

            if (! $locked || $locked->status !== SosStatus::Active->value) {
                return 0;
            }

            $hasEngagedResponder = $locked->responders()
                ->where('status', SosResponderStatus::Engaged->value)
                ->exists();

            if ($hasEngagedResponder) {
                return 0;
            }

            $ageSeconds = $locked->activated_at?->diffInSeconds(now()) ?? 0;
            $processed = 0;

            if ($ageSeconds >= 60 && $locked->escalation_stage < 1) {
                $this->recordStage($locked, 1, 'notify_secondary_responders', 'queued');
                $this->notifications->queueStageOne($locked);
                $locked->escalation_stage = 1;
                $locked->save();
                event(new SosEscalated($this->payload($locked, 1, 'notify_secondary_responders', 'queued')));
                $processed++;
            }

            if ($ageSeconds >= 180 && $locked->escalation_stage < 2) {
                $this->recordStage($locked, 2, 'sms_fallback', 'pending_provider');
                $locked->escalation_stage = 2;
                $locked->save();
                event(new SosEscalated($this->payload($locked, 2, 'sms_fallback', 'pending_provider')));
                $processed++;
            }

            if ($ageSeconds >= 300 && $locked->escalation_stage < 3) {
                $this->recordStage($locked, 3, 'show_local_emergency_number', 'client_action_required');
                $locked->escalation_stage = 3;
                $locked->save();
                event(new SosEscalated($this->payload($locked, 3, 'show_local_emergency_number', 'client_action_required')));
                $processed++;
            }

            return $processed;
        });
    }

    private function recordStage(SosEvent $event, int $stage, string $action, string $status): void
    {
        SosEscalation::query()->firstOrCreate(
            ['sos_event_id' => $event->id, 'stage' => $stage],
            [
                'action' => $action,
                'status' => $status,
                'context' => ['provider_integration_required' => $stage === 2],
                'occurred_at' => now(),
            ],
        );
    }

    private function payload(SosEvent $event, int $stage, string $action, string $status): array
    {
        return [
            'channel' => 'orbit.sos.'.$event->id,
            'event_name' => 'sos.escalated',
            'payload' => [
                'sos_id' => $event->id,
                'circle_id' => $event->circle_id,
                'stage' => $stage,
                'action' => $action,
                'status' => $status,
            ],
        ];
    }
}
