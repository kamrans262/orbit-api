<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PurgeIdentityAuditLogsCommand extends Command
{
    protected $signature = 'orbit:identity:purge-audit-logs';

    protected $description = 'Purge primary-store security audit rows older than the one-year retention window.';

    public function handle(): int
    {
        $deleted = DB::table('audit_logs')
            ->where('occurred_at', '<', now()->subDays(366))
            ->delete();

        $this->info("Purged {$deleted} expired audit log row(s) from the primary store.");

        return self::SUCCESS;
    }
}
