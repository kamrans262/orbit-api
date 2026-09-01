<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AccountDeletionRequest;
use App\Modules\Identity\Actions\FinalizeAccountDeletionAction;
use Illuminate\Console\Command;

final class FinalizeAccountDeletionsCommand extends Command
{
    protected $signature = 'orbit:identity:finalize-deletions {--limit=100}';

    protected $description = 'Finalize due Orbit account deletions after the reversible 30-day grace period.';

    public function handle(FinalizeAccountDeletionAction $action): int
    {
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $counts = ['completed' => 0, 'blocked_owner' => 0, 'not_due' => 0];

        AccountDeletionRequest::query()
            ->whereIn('status', ['pending', 'blocked'])
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get()
            ->each(function (AccountDeletionRequest $deletion) use ($action, &$counts): void {
                $result = $action->handle($deletion);
                $counts[$result] = ($counts[$result] ?? 0) + 1;
            });

        $this->info(sprintf(
            'Identity deletions: %d completed, %d blocked by Circle ownership.',
            $counts['completed'],
            $counts['blocked_owner'],
        ));

        return self::SUCCESS;
    }
}
