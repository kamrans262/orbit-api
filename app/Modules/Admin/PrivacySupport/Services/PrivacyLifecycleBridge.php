<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Services;

use App\Models\AccountDeletionRequest;
use App\Models\DataExportRequest;
use App\Models\PrivacyRequest;
use Illuminate\Support\Facades\Schema;

final readonly class PrivacyLifecycleBridge
{
    public function syncExport(DataExportRequest $export): ?PrivacyRequest
    {
        if (! Schema::hasTable('privacy_requests')) {
            return null;
        }

        $status = match ($export->status) {
            'ready' => 'completed',
            'expired' => 'completed',
            'failed' => 'waiting_user',
            default => 'in_progress',
        };

        return PrivacyRequest::query()->updateOrCreate(
            ['linked_data_export_id' => $export->id],
            [
                'user_id' => $export->user_id,
                'type' => 'data_export',
                'source' => 'identity',
                'status' => $status,
                'identity_status' => 'account_authenticated',
                'deadline_at' => $export->requested_at?->addDays(30),
                'completed_at' => $export->completed_at,
            ],
        );
    }

    public function syncDeletion(AccountDeletionRequest $deletion): ?PrivacyRequest
    {
        if (! Schema::hasTable('privacy_requests')) {
            return null;
        }

        $status = match ($deletion->status) {
            'pending' => 'in_progress',
            'blocked' => 'waiting_user',
            'cancelled' => 'cancelled',
            'completed' => 'completed',
            default => 'new',
        };

        return PrivacyRequest::query()->updateOrCreate(
            ['linked_deletion_id' => $deletion->id],
            [
                'user_id' => $deletion->user_id,
                'type' => 'account_deletion',
                'source' => 'identity',
                'status' => $status,
                'identity_status' => 'account_authenticated',
                'details' => $deletion->reason,
                'resolution' => $deletion->blocking_reason,
                'deadline_at' => $deletion->scheduled_for,
                'completed_at' => $deletion->completed_at ?? $deletion->cancelled_at,
            ],
        );
    }
}
