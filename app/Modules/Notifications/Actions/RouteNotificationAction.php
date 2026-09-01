<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Models\NotificationDelivery;
use App\Models\OrbitNotification;
use App\Modules\Notifications\Enums\NotificationPriority;
use App\Modules\Notifications\Events\NotificationCreated;
use App\Modules\Notifications\Services\NotificationDeliveryPayloadFactory;
use App\Modules\Notifications\Services\NotificationDeviceService;
use App\Modules\Notifications\Services\NotificationPayloadSanitizer;
use App\Modules\Notifications\Services\NotificationPolicy;
use Illuminate\Support\Facades\DB;

final readonly class RouteNotificationAction
{
    public function __construct(
        private NotificationPayloadSanitizer $sanitizer,
        private NotificationPolicy $policy,
        private NotificationDeviceService $devices,
        private NotificationDeliveryPayloadFactory $payloadFactory,
    ) {}

    public function handle(
        int $targetUserId,
        string $kind,
        string $idempotencyKey,
        array $payload,
        NotificationPriority $priority = NotificationPriority::Normal,
        ?string $circleId = null,
        ?string $deepLink = null,
    ): ?OrbitNotification {
        $routing = $this->policy->resolve($targetUserId, $kind, $circleId);

        if (! $routing['in_app'] && ! $routing['push']) {
            return null;
        }

        $safePayload = $this->sanitizer->sanitize($kind, $payload);
        $deepLink ??= isset($safePayload['deep_link']) ? (string) $safePayload['deep_link'] : null;
        unset($safePayload['deep_link']);

        $created = false;

        $notification = DB::transaction(function () use (
            $targetUserId, $kind, $idempotencyKey, $safePayload, $priority, $circleId, $deepLink, $routing, &$created
        ): OrbitNotification {
            $existing = OrbitNotification::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                return $existing;
            }

            $notification = OrbitNotification::query()->create([
                'user_id' => $targetUserId,
                'circle_id' => $circleId,
                'kind' => $kind,
                'priority' => $priority->value,
                'idempotency_key' => $idempotencyKey,
                'summary' => $this->summary($kind),
                'payload' => $safePayload,
                'deep_link' => $deepLink,
                'in_app_visible' => $routing['in_app'],
            ]);
            $created = true;

            if ($routing['push']) {
                foreach ($this->devices->pushReadyDevices($targetUserId) as $device) {
                    $provider = match ($device['platform']) {
                        'ios' => 'apns',
                        'android' => 'fcm',
                        default => 'generic',
                    };

                    NotificationDelivery::query()->firstOrCreate(
                        [
                            'notification_id' => $notification->id,
                            'device_id' => $device['id'],
                            'channel' => 'push',
                        ],
                        [
                            'target_user_id' => $targetUserId,
                            'provider' => $provider,
                            'priority' => $priority->value,
                            'collapse_key' => $circleId ? 'orbit.circle.'.$circleId.'.'.$this->family($kind) : null,
                            'silent' => $routing['silent'],
                            'payload' => $this->payloadFactory->forDevice($notification, $device['platform'], $routing['silent']),
                            'status' => 'pending_provider',
                            'available_at' => now(),
                            'attempts' => 0,
                        ],
                    );
                }
            }

            return $notification;
        });

        if ($created && $routing['in_app']) {
            event(new NotificationCreated([
                'channel' => 'orbit.user.'.$targetUserId,
                'event_name' => 'notification.created',
                'payload' => [
                    'id' => $notification->id,
                    'kind' => $notification->kind,
                    'priority' => $notification->priority,
                    'summary' => $notification->summary,
                    'circle_id' => $notification->circle_id,
                    'payload' => $notification->payload ?? [],
                    'deep_link' => $notification->deep_link,
                    'created_at' => $notification->created_at?->toIso8601String(),
                ],
            ]));
        }

        return $notification;
    }

    private function summary(string $kind): string
    {
        return match (true) {
            str_starts_with($kind, 'sos.') => 'SOS alert',
            str_starts_with($kind, 'ping.') => 'New Ping',
            str_starts_with($kind, 'message.') => 'New message',
            str_starts_with($kind, 'moment.') => 'New Moment',
            default => 'Orbit update',
        };
    }

    private function family(string $kind): string
    {
        return explode('.', $kind, 2)[0];
    }
}
