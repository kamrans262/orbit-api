<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminSosExport;
use Illuminate\Console\Command;

final class PurgeExpiredAdminSosExportsCommand extends Command
{
    protected $signature = 'orbit:admin:sos:purge-expired-exports';

    protected $description = 'Purge expired temporary administrator SOS export snapshots.';

    public function handle(): int
    {
        $count = AdminSosExport::query()
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Purged {$count} expired administrator SOS export(s).");

        return self::SUCCESS;
    }
}
