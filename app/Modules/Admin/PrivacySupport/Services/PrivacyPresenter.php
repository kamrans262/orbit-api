<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Services;

use App\Models\AccountDeletionRequest;
use App\Models\DataExportRequest;
use App\Models\PrivacyExportDeliveryLink;
use App\Models\PrivacyRequest;

final class PrivacyPresenter
{
    public function request(PrivacyRequest $request): array
    {
        return [
            'id' => $request->id,
            'user_id' => $request->user_id,
            'type' => $request->type,
            'source' => $request->source,
            'status' => $request->status,
            'identity_status' => $request->identity_status,
            'assigned_admin_id' => $request->assigned_admin_id,
            'details' => $request->details,
            'resolution' => $request->resolution,
            'deadline_at' => $request->deadline_at?->toIso8601String(),
            'completed_at' => $request->completed_at?->toIso8601String(),
            'linked_data_export_id' => $request->linked_data_export_id,
            'linked_deletion_id' => $request->linked_deletion_id,
            'created_at' => $request->created_at?->toIso8601String(),
            'updated_at' => $request->updated_at?->toIso8601String(),
        ];
    }

    public function export(DataExportRequest $export): array
    {
        return [
            'id' => $export->id,
            'user_id' => $export->user_id,
            'status' => $export->status,
            'payload_available' => $export->payload !== null,
            'requested_at' => $export->requested_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
            'delivery_links' => PrivacyExportDeliveryLink::query()
                ->where('data_export_request_id', $export->id)
                ->latest()
                ->get()
                ->map(fn (PrivacyExportDeliveryLink $link): array => [
                    'id' => $link->id,
                    'expires_at' => $link->expires_at?->toIso8601String(),
                    'delivered_at' => $link->delivered_at?->toIso8601String(),
                    'revoked_at' => $link->revoked_at?->toIso8601String(),
                ])->all(),
        ];
    }

    public function deletion(AccountDeletionRequest $deletion): array
    {
        return [
            'id' => $deletion->id,
            'user_id' => $deletion->user_id,
            'status' => $deletion->status,
            'reason' => $deletion->reason,
            'blocking_reason' => $deletion->blocking_reason,
            'requested_at' => $deletion->requested_at?->toIso8601String(),
            'scheduled_for' => $deletion->scheduled_for?->toIso8601String(),
            'cancelled_at' => $deletion->cancelled_at?->toIso8601String(),
            'completed_at' => $deletion->completed_at?->toIso8601String(),
        ];
    }
}
