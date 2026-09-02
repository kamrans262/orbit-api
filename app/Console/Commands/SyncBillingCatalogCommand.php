<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Admin\BillingAdvertising\Services\BillingCatalogService;
use Illuminate\Console\Command;

final class SyncBillingCatalogCommand extends Command
{
    protected $signature = 'orbit:billing:sync-catalog';

    protected $description = 'Synchronize Orbit Free, Lite, Plus plans and built-in entitlement definitions.';

    public function handle(BillingCatalogService $service): int
    {
        $service->syncDefaults();
        $this->info('Orbit billing catalog synchronized.');

        return self::SUCCESS;
    }
}
