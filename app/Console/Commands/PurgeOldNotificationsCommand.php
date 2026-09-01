<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OrbitNotification;
use Illuminate\Console\Command;

final class PurgeOldNotificationsCommand extends Command
{
    protected $signature = 'orbit:notifications:purge-old';

    protected $description = 'Purge Orbit notification inbox records older than 90 days.';

    public function handle(): int
    {
        $count = OrbitNotification::query()->where('created_at', '<', now()->subDays(90))->delete();
        $this->info("Purged {$count} old notification(s).");

        return self::SUCCESS;
    }
}
