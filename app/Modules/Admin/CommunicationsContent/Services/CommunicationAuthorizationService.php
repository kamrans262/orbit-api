<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Services;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\CommunicationsContent\Exceptions\CommunicationsContentException;

final class CommunicationAuthorizationService
{
    public function assertEmergency(AdminUser $actor, ?AdminSession $session): void
    {
        if (! $actor->hasPermission('communications.emergency.send')) {
            throw new CommunicationsContentException(
                'COMMUNICATION_EMERGENCY_FORBIDDEN',
                'Emergency or sensitive communications require a separately assigned permission.',
                403,
            );
        }

        $window = max(1, (int) config('orbit_admin.reauth_window_minutes', 10));
        if (! $session || ! $session->reauthenticated_at || $session->reauthenticated_at->lt(now()->subMinutes($window))) {
            throw new CommunicationsContentException(
                'ADMIN_REAUTH_REQUIRED',
                'Recent administrator reauthentication is required for emergency or sensitive communications.',
                428,
            );
        }
    }
}
