<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AccountDeletionRequest;
use App\Models\DataExportRequest;
use App\Modules\Admin\PrivacySupport\Services\PrivacyLifecycleBridge;
use Illuminate\Console\Command;

final class SyncIdentityPrivacyRequestsCommand extends Command
{
    protected $signature = 'orbit:privacy:sync-identity-requests';

    protected $description = 'Synchronize existing Identity data export and account deletion requests into privacy case tracking.';

    public function handle(PrivacyLifecycleBridge $bridge): int
    {
        $exports = 0;
        foreach (DataExportRequest::query()->orderBy('requested_at')->cursor() as $export) {
            $bridge->syncExport($export);
            $exports++;
        }

        $deletions = 0;
        foreach (AccountDeletionRequest::query()->orderBy('requested_at')->cursor() as $deletion) {
            $bridge->syncDeletion($deletion);
            $deletions++;
        }

        $this->info("Synchronized {$exports} export request(s) and {$deletions} deletion request(s).");

        return self::SUCCESS;
    }
}
