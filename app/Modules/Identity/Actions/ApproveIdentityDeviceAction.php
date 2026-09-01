<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\IdentityDeviceTrust;
use App\Models\User;
use App\Modules\Identity\Services\AuditLogger;
use App\Modules\Identity\Services\DeviceTrustService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ApproveIdentityDeviceAction
{
    public function __construct(
        private readonly DeviceTrustService $devices,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, string $deviceId, string $approverDeviceId, ?Request $request = null): IdentityDeviceTrust
    {
        $this->devices->assertOwnedDevice((int) $user->getKey(), $deviceId);
        $this->devices->assertOwnedDevice((int) $user->getKey(), $approverDeviceId);

        $approver = IdentityDeviceTrust::query()
            ->where('user_id', $user->getKey())
            ->where('device_id', $approverDeviceId)
            ->where('status', 'trusted')
            ->first();

        if (! $approver) {
            throw ValidationException::withMessages([
                'approver_device_id' => 'Approval must come from an already trusted device.',
            ]);
        }

        $trust = $this->devices->ensureTrustState((int) $user->getKey(), $deviceId);

        if ($trust->status === 'pending' && $trust->expires_at?->isPast()) {
            throw ValidationException::withMessages([
                'device_id' => 'This device approval request has expired. Start a new secure session request.',
            ]);
        }

        if ($trust->status !== 'trusted') {
            $trust->forceFill([
                'status' => 'trusted',
                'approved_by_device_id' => $approverDeviceId,
                'decided_at' => now(),
                'expires_at' => null,
            ])->save();
        }

        $this->audit->write(
            'identity.device.approved',
            (int) $user->getKey(),
            targetType: 'device',
            targetId: $deviceId,
            metadata: ['approved_by_device_id' => $approverDeviceId],
            request: $request,
        );

        return $trust;
    }
}
