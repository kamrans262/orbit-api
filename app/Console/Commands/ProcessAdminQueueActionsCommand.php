<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminQueueAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class ProcessAdminQueueActionsCommand extends Command
{
    protected $signature = 'orbit:operations:process-queue-actions {--limit=50}';

    protected $description = 'Process approved administrator queue retry/quarantine requests.';

    public function handle(): int
    {
        foreach (AdminQueueAction::query()->where('status', 'requested')->oldest()->limit((int) $this->option('limit'))->get() as $a) {
            if ($a->action === 'quarantine') {
                $a->forceFill(['status' => 'quarantined', 'processed_at' => now(), 'result_message' => 'Failed job quarantined from administrator retry workflow.'])->save();

                continue;
            } $code = Artisan::call('queue:retry', ['id' => [$a->failed_job_uuid]]);
            $a->forceFill(['status' => $code === 0 ? 'processed' : 'failed', 'processed_at' => now(), 'result_message' => mb_substr(Artisan::output(), 0, 1000)])->save();
        }

return self::SUCCESS;
    }
}
