<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Admin\Services\AdminRbacService;
use Illuminate\Console\Command;

final class SyncAdminRbacCommand extends Command
{
    protected $signature = 'orbit:admin:sync-rbac';

    protected $description = 'Create or update Orbit administrative foundation roles and permissions.';

    public function handle(AdminRbacService $rbac): int
    {
        $rbac->syncDefaults();
        $this->info('Orbit administrator roles and permissions synchronized.');

        return self::SUCCESS;
    }
}
