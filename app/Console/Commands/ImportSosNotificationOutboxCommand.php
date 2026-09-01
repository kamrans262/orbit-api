<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Notifications\Actions\ImportSosNotificationOutboxAction;
use Illuminate\Console\Command;

final class ImportSosNotificationOutboxCommand extends Command
{
    protected $signature = 'orbit:notifications:import-sos';

    protected $description = 'Import pending SOS push intents into Orbit notification deliveries.';

    public function handle(ImportSosNotificationOutboxAction $importer): int
    {
        $count = $importer->handle();
        $this->info("Imported {$count} SOS notification intent(s).");

        return self::SUCCESS;
    }
}
