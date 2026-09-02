<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Admin\Moderation\Services\ModerationIntakeService;
use Illuminate\Console\Command;

final class ImportActivityReportsToModerationCommand extends Command
{
    protected $signature = 'orbit:moderation:import-activity-reports';

    protected $description = 'Import legacy Orbit Activity reports into the unified moderation queue idempotently.';

    public function handle(ModerationIntakeService $intake): int
    {
        $count = $intake->importExistingActivityReports();
        $this->info("Imported {$count} Activity report(s) into moderation.");

        return self::SUCCESS;
    }
}
