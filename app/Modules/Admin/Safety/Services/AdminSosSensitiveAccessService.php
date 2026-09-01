<?php

declare(strict_types=1);

namespace App\Modules\Admin\Safety\Services;

use App\Models\AdminSession;
use App\Models\AdminSosSensitiveAccess;
use App\Models\AdminUser;
use App\Models\SosEvent;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\Request;

final readonly class AdminSosSensitiveAccessService
{
    public function __construct(private AdminAuditLogger $audit) {}

    /** @return array<string,mixed> */
    public function location(
        SosEvent $incident,
        AdminUser $admin,
        AdminSession $session,
        string $purpose,
        string $reason,
        Request $request,
    ): array {
        $this->record($incident, $admin, $session, 'location', $purpose, $reason, $request);

        $this->audit->write(
            'admin.sos.sensitive.location.viewed',
            $admin,
            $session,
            'sos_event',
            $incident->id,
            reason: $reason,
            metadata: ['purpose' => $purpose, 'available' => $incident->last_location_at !== null],
            request: $request,
        );

        return [
            'available' => $incident->last_location_at !== null,
            'latitude' => $incident->last_location_at !== null ? $incident->last_latitude : null,
            'longitude' => $incident->last_location_at !== null ? $incident->last_longitude : null,
            'accuracy_meters' => $incident->last_location_at !== null ? $incident->last_location_accuracy_m : null,
            'updated_at' => $incident->last_location_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    public function recording(
        SosEvent $incident,
        AdminUser $admin,
        AdminSession $session,
        string $purpose,
        string $reason,
        Request $request,
    ): array {
        $this->record($incident, $admin, $session, 'recording', $purpose, $reason, $request);

        $this->audit->write(
            'admin.sos.sensitive.recording.viewed',
            $admin,
            $session,
            'sos_event',
            $incident->id,
            reason: $reason,
            metadata: ['purpose' => $purpose, 'available' => $incident->recording_ref !== null],
            request: $request,
        );

        return [
            'available' => $incident->recording_ref !== null,
            'encrypted_recording_reference' => $incident->recording_ref,
            'expires_at' => $incident->recording_expires_at?->toIso8601String(),
            'plaintext_available_to_admin' => false,
            'decryption_keys_exposed' => false,
        ];
    }

    private function record(
        SosEvent $incident,
        AdminUser $admin,
        AdminSession $session,
        string $accessType,
        string $purpose,
        string $reason,
        Request $request,
    ): void {
        AdminSosSensitiveAccess::query()->create([
            'sos_event_id' => $incident->id,
            'admin_user_id' => $admin->id,
            'admin_session_id' => $session->id,
            'access_type' => $accessType,
            'purpose' => $purpose,
            'reason' => $reason,
            'request_id' => AdminApiResponse::requestId($request),
            'occurred_at' => now(),
        ]);
    }
}
