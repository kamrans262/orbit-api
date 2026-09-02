<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MaintenanceWindow;
use Illuminate\Console\Command;

final class CloseExpiredMaintenanceWindowsCommand extends Command
{
    protected $signature = 'orbit:maintenance:close-expired';

    protected $description = 'Mark elapsed active maintenance windows complete.';

    public function handle(): int
    {
        $count = MaintenanceWindow::query()->where('status', 'active')->whereNotNull('ends_at')->where('ends_at', '<=', now())->update(['status' => 'completed', 'updated_at' => now()]);
        $this->info("Completed {$count} expired maintenance window(s).");

        return self::SUCCESS;
    }
}
