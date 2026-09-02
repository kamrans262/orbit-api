<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Services;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\DataExportRequest;
use App\Models\PrivacyExportDeliveryLink;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Modules\Admin\PrivacySupport\Exceptions\PrivacySupportDomainException;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Identity\Actions\RequestDataExportAction;
use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AdminDataExportService
{
    public function __construct(
        private AdminAuditLogger $audit,
        private ContactHistoryService $contacts,
    ) {}

    public function generateForPrivacyRequest(
        PrivacyRequest $privacy,
        AdminUser $admin,
        AdminSession $session,
        string $reason,
        Request $request,
    ): DataExportRequest {
        if (! in_array($privacy->type, ['access', 'data_export'], true)) {
            throw new PrivacySupportDomainException('PRIVACY_EXPORT_TYPE_INVALID', 409, 'This privacy request does not require a data export.');
        }

        if (! in_array($privacy->identity_status, ['verified', 'account_authenticated'], true)) {
            throw new PrivacySupportDomainException('PRIVACY_IDENTITY_NOT_VERIFIED', 409, 'Identity verification is required before generating a data export.');
        }

        $user = User::query()->find($privacy->user_id);
        if ($user === null) {
            throw new PrivacySupportDomainException('EXPORT_USER_NOT_FOUND', 404, 'Export user not found.');
        }

        $export = app(RequestDataExportAction::class)->handle($user, $request);

        $autoCase = PrivacyRequest::query()
            ->where('linked_data_export_id', $export->id)
            ->where('id', '!=', $privacy->id)
            ->first();
        $autoCase?->delete();

        $privacy->forceFill([
            'linked_data_export_id' => $export->id,
            'status' => 'in_progress',
            'completed_at' => null,
        ])->save();

        $this->audit->write(
            'admin.privacy.export.generated', $admin, $session, 'privacy_request', $privacy->id,
            reason: $reason, after: ['data_export_id' => $export->id], request: $request,
        );

        return $export;
    }

    public function createDeliveryLink(
        DataExportRequest $export,
        AdminUser $admin,
        AdminSession $session,
        string $reason,
        Request $request,
    ): array {
        if ($export->status !== 'ready' || $export->payload === null || $export->expires_at?->isPast()) {
            throw new PrivacySupportDomainException('EXPORT_NOT_DELIVERABLE', 409, 'The data export is not currently deliverable.');
        }

        $plainToken = Str::random(64);
        $oneDay = now()->addDay();
        $linkExpiresAt = $export->expires_at->lt($oneDay) ? $export->expires_at : $oneDay;

        $link = PrivacyExportDeliveryLink::query()->create([
            'data_export_request_id' => $export->id,
            'user_id' => $export->user_id,
            'token_hash' => hash('sha256', $plainToken),
            'created_by_admin_id' => $admin->id,
            'expires_at' => $linkExpiresAt,
        ]);

        $this->audit->write(
            'admin.privacy.export.delivery_link.created', $admin, $session, 'data_export', $export->id,
            reason: $reason, after: ['delivery_link_id' => $link->id, 'expires_at' => $link->expires_at?->toIso8601String()],
            request: $request,
        );

        $this->contacts->record(
            (int) $export->user_id, 'privacy.export.ready', 'system', 'outbound',
            'Your Orbit data export is ready', 'A time-limited data export delivery link was generated.',
            'data_export', $export->id, $admin, ['delivery_link_id' => $link->id],
        );

        if (class_exists(RouteNotificationAction::class)) {
            app(RouteNotificationAction::class)->handle(
                (int) $export->user_id,
                'privacy.export_ready',
                'privacy-export-ready:'.$link->id,
                ['resource_id' => $export->id, 'actor_user_id' => (int) $export->user_id, 'deep_link' => '/profile/privacy'],
                NotificationPriority::High,
            );
        }

        return [
            'id' => $link->id,
            'delivery_token' => $plainToken,
            'delivery_path' => '/api/v1/privacy/export-deliveries/'.$plainToken,
            'expires_at' => $link->expires_at?->toIso8601String(),
        ];
    }

    public function revokeDeliveryLink(
        DataExportRequest $export,
        PrivacyExportDeliveryLink $link,
        AdminUser $admin,
        AdminSession $session,
        string $reason,
        Request $request,
    ): void {
        if ($link->data_export_request_id !== $export->id) {
            throw new PrivacySupportDomainException('EXPORT_LINK_NOT_FOUND', 404, 'Export delivery link not found.');
        }

        if ($link->revoked_at === null) {
            $link->forceFill(['revoked_at' => now()])->save();
        }

        $this->audit->write(
            'admin.privacy.export.delivery_link.revoked', $admin, $session, 'data_export', $export->id,
            reason: $reason, after: ['delivery_link_id' => $link->id, 'revoked' => true], request: $request,
        );
    }

    public function regenerate(
        DataExportRequest $export,
        AdminUser $admin,
        AdminSession $session,
        string $reason,
        Request $request,
    ): DataExportRequest {
        if ($export->status === 'ready' && $export->payload !== null && $export->expires_at?->isFuture()) {
            throw new PrivacySupportDomainException('EXPORT_STILL_ACTIVE', 409, 'The existing export is still active and does not need regeneration.');
        }

        $user = User::query()->find($export->user_id);
        if ($user === null) {
            throw new PrivacySupportDomainException('EXPORT_USER_NOT_FOUND', 404, 'Export user not found.');
        }

        $newExport = DB::transaction(function () use ($export, $user, $request): DataExportRequest {
            $export->forceFill(['status' => 'expired', 'payload' => null, 'expires_at' => now()])->save();

            return app(RequestDataExportAction::class)->handle($user, $request);
        });

        $this->audit->write(
            'admin.privacy.export.regenerated', $admin, $session, 'data_export', $newExport->id,
            reason: $reason, metadata: ['replaces_export_id' => $export->id], request: $request,
        );

        return $newExport;
    }
}
