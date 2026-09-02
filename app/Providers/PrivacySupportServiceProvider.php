<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AccountDeletionRequest;
use App\Models\DataExportRequest;
use App\Models\ModerationAppeal;
use App\Models\ModerationEnforcement;
use App\Modules\Admin\PrivacySupport\Services\ContactHistoryService;
use App\Modules\Admin\PrivacySupport\Services\PrivacyLifecycleBridge;
use Illuminate\Support\ServiceProvider;

final class PrivacySupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        DataExportRequest::saved(function (DataExportRequest $export): void {
            app(PrivacyLifecycleBridge::class)->syncExport($export);
        });

        AccountDeletionRequest::saved(function (AccountDeletionRequest $deletion): void {
            app(PrivacyLifecycleBridge::class)->syncDeletion($deletion);
        });

        ModerationEnforcement::created(function (ModerationEnforcement $enforcement): void {
            if ($enforcement->target_type !== 'user' || ! ctype_digit((string) $enforcement->target_id)) {
                return;
            }

            app(ContactHistoryService::class)->recordOnce(
                (int) $enforcement->target_id,
                'moderation.enforcement.applied',
                'system',
                'outbound',
                'Account enforcement',
                'An Orbit account enforcement action was applied.',
                'moderation_enforcement',
                $enforcement->id,
                metadata: ['action' => $enforcement->action, 'status' => $enforcement->status],
            );
        });

        ModerationAppeal::saved(function (ModerationAppeal $appeal): void {
            if (! in_array($appeal->status, ['decided', 'completed', 'second_reviewed'], true) || $appeal->outcome === null) {
                return;
            }

            app(ContactHistoryService::class)->recordOnce(
                (int) $appeal->user_id,
                'moderation.appeal.'.$appeal->outcome,
                'system',
                'outbound',
                'Appeal decision',
                'An Orbit moderation appeal decision was recorded.',
                'moderation_appeal',
                $appeal->id,
                metadata: ['outcome' => $appeal->outcome],
            );
        });
    }
}
