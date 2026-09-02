<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Services;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Models\OrbitNotification;
use App\Modules\Admin\CommunicationsContent\Exceptions\CommunicationsContentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final readonly class CampaignService
{
    public function __construct(
        private AudienceResolver $audience,
        private CommunicationAuthorizationService $authorization,
    ) {}

    public function preview(CommunicationCampaign $campaign): array
    {
        $ids = $this->audience->userIds($campaign->audience ?? []);

        return ['target_count' => count($ids), 'sample_user_ids' => array_slice($ids, 0, 20)];
    }

    public function schedule(CommunicationCampaign $campaign, \DateTimeInterface $when, ?AdminUser $actor = null, ?AdminSession $session = null): CommunicationCampaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw new CommunicationsContentException('COMMUNICATION_CAMPAIGN_FINAL', 'Only draft or scheduled campaigns can be scheduled.');
        }

        if ($campaign->is_emergency) {
            if ($actor) {
                $this->authorization->assertEmergency($actor, $session);
                $campaign->forceFill(['approved_by_admin_id' => $actor->id, 'approved_at' => now()])->save();
            } elseif (! $campaign->approved_by_admin_id || ! $campaign->approved_at) {
                throw new CommunicationsContentException('COMMUNICATION_EMERGENCY_APPROVAL_REQUIRED', 'Emergency communications require explicit prior approval.', 403);
            }
        }

        $campaign->forceFill([
            'status' => 'scheduled',
            'scheduled_at' => $when,
            'cancelled_at' => null,
            'approved_by_admin_id' => $campaign->is_emergency ? ($actor?->id ?? $campaign->approved_by_admin_id) : $campaign->approved_by_admin_id,
            'approved_at' => $campaign->is_emergency ? ($actor ? now() : $campaign->approved_at) : $campaign->approved_at,
        ])->save();

        return $campaign->refresh();
    }

    public function cancel(CommunicationCampaign $campaign): CommunicationCampaign
    {
        if (in_array($campaign->status, ['sent', 'cancelled'], true)) {
            throw new CommunicationsContentException('COMMUNICATION_CAMPAIGN_FINAL', 'A sent or cancelled campaign cannot be cancelled again.');
        }

        $campaign->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

        return $campaign->refresh();
    }

    public function send(CommunicationCampaign $campaign, ?AdminUser $actor = null, ?AdminSession $session = null, ?array $testUserIds = null): CommunicationCampaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true) && $testUserIds === null) {
            throw new CommunicationsContentException('COMMUNICATION_CAMPAIGN_FINAL', 'Only draft or scheduled campaigns can be sent.');
        }

        if ($campaign->is_emergency) {
            if ($actor) {
                $this->authorization->assertEmergency($actor, $session);
                $campaign->forceFill(['approved_by_admin_id' => $actor->id, 'approved_at' => now()])->save();
            } elseif (! $campaign->approved_by_admin_id || ! $campaign->approved_at) {
                throw new CommunicationsContentException('COMMUNICATION_EMERGENCY_APPROVAL_REQUIRED', 'Emergency communications require explicit prior approval.', 403);
            }
        }

        $userIds = $testUserIds !== null
            ? array_values(array_unique(array_map('intval', $testUserIds)))
            : $this->audience->userIds($campaign->audience ?? []);

        DB::transaction(function () use ($campaign, $userIds, $testUserIds): void {
            if ($campaign->channel === 'system_banner') {
                $this->publishSystemBanner($campaign);
            }

            foreach ($userIds as $userId) {
                $this->deliverToUser($campaign, $userId, $testUserIds !== null);
            }

            if ($testUserIds === null) {
                $stats = $this->stats($campaign);
                $campaign->forceFill([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'stats' => $stats,
                ])->save();
            }
        });

        return $campaign->refresh();
    }

    public function stats(CommunicationCampaign $campaign): array
    {
        $query = CommunicationDelivery::query()->where('campaign_id', $campaign->id);

        return [
            'targeted' => (clone $query)->count(),
            'delivered' => (clone $query)->whereIn('status', ['delivered', 'published', 'dispatched'])->count(),
            'pending_provider' => (clone $query)->where('status', 'pending_provider')->count(),
            'provider_unconfigured' => (clone $query)->where('status', 'provider_unconfigured')->count(),
            'no_device' => (clone $query)->where('status', 'no_device')->count(),
            'suppressed' => (clone $query)->where('status', 'suppressed_preference')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'opened' => (clone $query)->whereNotNull('opened_at')->count(),
        ];
    }

    private function deliverToUser(CommunicationCampaign $campaign, int $userId, bool $test): void
    {
        $existing = CommunicationDelivery::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $userId)
            ->where('channel', $campaign->channel)
            ->first();
        if ($existing) {
            return;
        }

        $status = 'delivered';
        $provider = null;

        if (in_array($campaign->channel, ['in_app', 'push'], true)) {
            [$status, $provider] = $this->routeOrbitNotification($campaign, $userId, $test);
        } elseif ($campaign->channel === 'email') {
            $status = 'pending_provider';
            $provider = 'laravel_mail';
        } elseif ($campaign->channel === 'sms') {
            $status = 'provider_unconfigured';
            $provider = 'sms_unconfigured';
        } elseif ($campaign->channel === 'system_banner') {
            $status = 'published';
            $provider = 'orbit_banner';
        }

        CommunicationDelivery::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $userId,
            'channel' => $campaign->channel,
            'status' => $status,
            'provider' => $provider,
            'queued_at' => now(),
            'delivered_at' => in_array($status, ['delivered', 'published'], true) ? now() : null,
        ]);
    }

    /** @return array{0:string,1:string} */
    private function routeOrbitNotification(CommunicationCampaign $campaign, int $userId, bool $test): array
    {
        $prefs = Schema::hasTable('notification_preferences')
            ? DB::table('notification_preferences')->where('user_id', $userId)->first()
            : null;
        $emergency = (bool) $campaign->is_emergency;
        $inApp = $emergency || (bool) ($prefs?->in_app_enabled ?? true);
        $push = $emergency || (bool) ($prefs?->push_enabled ?? true);

        if ($campaign->channel === 'in_app' && ! $inApp) {
            return ['suppressed_preference', 'orbit_in_app_suppressed'];
        }
        if ($campaign->channel === 'push' && ! $push) {
            return ['suppressed_preference', 'orbit_push_suppressed'];
        }

        $key = ($test ? 'campaign-test:' : 'campaign:').$campaign->id.':user:'.$userId.':'.$campaign->channel;
        $notification = OrbitNotification::query()->where('idempotency_key', $key)->first();
        if (! $notification) {
            $notification = OrbitNotification::query()->create([
                'user_id' => $userId,
                'kind' => 'communication.campaign',
                'priority' => $emergency ? 'highest' : $campaign->priority,
                'idempotency_key' => $key,
                'summary' => mb_substr($campaign->title, 0, 120),
                'payload' => ['resource_id' => $campaign->id],
                'deep_link' => $campaign->deep_link,
                'in_app_visible' => $campaign->channel === 'in_app' ? $inApp : false,
            ]);
        }

        if ($campaign->channel === 'push') {
            $devices = DB::table('devices')->where('user_id', $userId)->whereNull('revoked_at');
            if (Schema::hasColumn('devices', 'push_token')) {
                $devices->whereNotNull('push_token')->where('push_token', '!=', '');
            }
            $rows = $devices->get();
            foreach ($rows as $device) {
                $provider = match (strtolower((string) ($device->platform ?? ''))) {
                    'ios' => 'apns',
                    'android' => 'fcm',
                    default => 'generic',
                };
                $deliveryExists = DB::table('notification_deliveries')
                    ->where('notification_id', $notification->id)
                    ->where('device_id', (string) $device->id)
                    ->where('channel', 'push')
                    ->exists();

                if (! $deliveryExists) {
                    DB::table('notification_deliveries')->insert([
                        'id' => (string) Str::uuid7(),
                        'notification_id' => $notification->id,
                        'target_user_id' => $userId,
                        'device_id' => (string) $device->id,
                        'channel' => 'push',
                        'provider' => $provider,
                        'priority' => $emergency ? 'highest' : $campaign->priority,
                        'collapse_key' => 'campaign:'.$campaign->id,
                        'silent' => false,
                        'payload' => json_encode([
                            'notification_id' => $notification->id,
                            'kind' => 'communication.campaign',
                            'summary' => $campaign->title,
                            'body' => $campaign->body,
                            'deep_link' => $campaign->deep_link,
                            'data' => ['resource_id' => $campaign->id],
                        ], JSON_THROW_ON_ERROR),
                        'status' => 'pending_provider',
                        'available_at' => now(),
                        'attempts' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return [$rows->isEmpty() ? 'no_device' : 'pending_provider', 'notification_delivery_boundary'];
        }

        return ['delivered', 'orbit_in_app'];
    }

    private function publishSystemBanner(CommunicationCampaign $campaign): void
    {
        $announcement = Announcement::query()->find($campaign->id);

        if (! $announcement) {
            $announcement = new Announcement([
                'type' => 'system_banner',
                'status' => 'published',
                'priority' => $campaign->priority,
                'dismissible' => ! $campaign->is_emergency,
                'deep_link' => $campaign->deep_link,
                'audience' => $campaign->audience,
                'starts_at' => now(),
                'published_at' => now(),
                'created_by_admin_id' => $campaign->created_by_admin_id,
                'published_by_admin_id' => $campaign->approved_by_admin_id,
            ]);

            // System-banner campaigns intentionally share their UUID with the
            // published announcement so operational tracing is one-to-one.
            // Set it directly instead of making `id` mass assignable.
            $announcement->id = $campaign->id;
            $announcement->save();
        }

        AnnouncementTranslation::query()->updateOrCreate(
            ['announcement_id' => $announcement->id, 'locale' => $campaign->locale],
            ['status' => 'published', 'title' => $campaign->title, 'body' => $campaign->body, 'published_at' => now()],
        );
    }
}
