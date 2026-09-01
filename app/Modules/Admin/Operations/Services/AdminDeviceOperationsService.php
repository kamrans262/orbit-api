<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Services;

use App\Models\AdminDeviceControl;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\Device;
use App\Models\IdentityDeviceTrust;
use App\Models\User;
use App\Modules\Admin\Services\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class AdminDeviceOperationsService
{
    public function __construct(private AdminConsumerSessionService $sessions, private AdminAuditLogger $audit) {}

    public function findOwned(User $user, string $deviceId): ?Device
    {
        return Device::query()->whereKey($deviceId)->where('user_id', $user->id)->first();
    }

    public function revoke(User $user, Device $device, AdminUser $admin, AdminSession $session, string $reason, Request $request): Device
    {
        return DB::transaction(function () use ($user, $device, $admin, $session, $reason, $request): Device {
            $before = $this->present($device);
            AdminDeviceControl::query()->updateOrCreate(
                ['device_id' => $device->id],
                [
                    'enforcement_revoked' => true,
                    'reason' => $reason,
                    'updated_by_admin_id' => $admin->id,
                ],
            );
            $device->forceFill(['revoked_at' => now(), 'push_token' => null])->save();
            IdentityDeviceTrust::query()->where('user_id', $user->id)->where('device_id', $device->id)->update([
                'status' => 'revoked', 'decided_at' => now(), 'updated_at' => now(),
            ]);
            $revoked = $this->sessions->revokeDeviceSessions($user, $device->id, 'admin_device_revoked');
            $this->audit->write(
                'admin.user.device.revoked', $admin, $session, 'device', $device->id,
                reason: $reason, before: $before, after: $this->present($device->refresh()), metadata: $revoked, request: $request,
            );

            return $device->refresh();
        });
    }

    public function updateControl(User $user, Device $device, AdminUser $admin, AdminSession $session, bool $suspicious, bool $requireVerification, string $reason, Request $request): AdminDeviceControl
    {
        return DB::transaction(function () use ($user, $device, $admin, $session, $suspicious, $requireVerification, $reason, $request): AdminDeviceControl {
            $control = AdminDeviceControl::query()->firstOrNew(['device_id' => $device->id]);
            $before = $control->exists ? $control->only(['suspicious', 'require_verification', 'enforcement_revoked', 'reason']) : [];
            $control->fill([
                'suspicious' => $suspicious,
                'require_verification' => $requireVerification,
                'reason' => $reason,
                'updated_by_admin_id' => $admin->id,
            ])->save();

            $revoked = null;
            if ($requireVerification) {
                IdentityDeviceTrust::query()->where('user_id', $user->id)->where('device_id', $device->id)->update([
                    'status' => 'pending', 'decided_at' => null, 'updated_at' => now(),
                ]);
                $revoked = $this->sessions->revokeDeviceSessions($user, $device->id, 'admin_device_verification_required');
            }

            $this->audit->write(
                'admin.user.device.controls.updated', $admin, $session, 'device', $device->id,
                reason: $reason, before: $before, after: $control->only(['suspicious', 'require_verification', 'enforcement_revoked', 'reason']),
                metadata: ['revoked' => $revoked], request: $request,
            );

            return $control->refresh();
        });
    }

    /** @return array{rotated:int} */
    public function rotate(User $user, Device $device, AdminUser $admin, AdminSession $session, string $reason, Request $request): array
    {
        $result = $this->sessions->forceAccessRotation($user, $device->id);
        $this->audit->write(
            'admin.user.device.token_rotation_forced', $admin, $session, 'device', $device->id,
            reason: $reason, metadata: $result, request: $request,
        );

        return $result;
    }

    /** @return array<string,mixed> */
    public function present(Device $device): array
    {
        $control = AdminDeviceControl::query()->whereKey($device->id)->first();
        $trust = IdentityDeviceTrust::query()->where('user_id', $device->user_id)->where('device_id', $device->id)->first();

        return [
            'id' => $device->id,
            'device_name' => $device->getAttribute('device_name') ?: $device->name,
            'platform' => $device->platform,
            'os_version' => $device->os_version,
            'app_version' => $device->app_version,
            'registered_at' => $device->created_at?->toIso8601String(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'revoked_at' => $device->revoked_at?->toIso8601String(),
            'push_token_health' => $device->push_token !== null && $device->revoked_at === null ? 'registered' : 'unavailable',
            'has_public_identity_key' => $device->public_identity_key !== null,
            'trust_status' => $trust?->status ?? 'unregistered',
            'suspicious' => (bool) ($control?->suspicious ?? false),
            'require_verification' => (bool) ($control?->require_verification ?? false),
            'enforcement_revoked' => (bool) ($control?->enforcement_revoked ?? false),
            'active_identity_sessions' => (int) DB::table('identity_sessions')->where('user_id', $device->user_id)->where('device_id', $device->id)->where('status', 'active')->count(),
        ];
    }
}
