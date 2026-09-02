<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DataExportRequest;
use App\Models\PrivacyExportDeliveryLink;
use Illuminate\Console\Command;

final class PurgeExpiredPrivacyExportsCommand extends Command
{
    protected $signature = 'orbit:privacy:purge-expired-exports';

    protected $description = 'Expire delivery links and redact expired user data export payloads.';

    public function handle(): int
    {
        $links = PrivacyExportDeliveryLink::query()
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now())
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        $exports = DataExportRequest::query()
            ->whereNotNull('payload')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'payload' => null,
                'updated_at' => now(),
            ]);

        $this->info("Expired {$links} delivery link(s) and redacted {$exports} export payload(s).");

        return self::SUCCESS;
    }
}
