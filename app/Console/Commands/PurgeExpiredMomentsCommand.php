<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Moment;
use App\Models\MomentView;
use App\Modules\Moments\Enums\MomentStatus;
use Illuminate\Console\Command;

final class PurgeExpiredMomentsCommand extends Command
{
    protected $signature = 'orbit:moments:purge-expired';

    protected $description = 'Expire old Moments and purge old Moment view receipts.';

    public function handle(): int
    {
        Moment::query()
            ->where('status', MomentStatus::Active)
            ->where('expires_at', '<=', now())
            ->update(['status' => MomentStatus::Expired->value]);

        $retentionDays = max(1, (int) config('orbit_moments.view_receipt_retention_days', 7));

        MomentView::query()
            ->whereHas('moment', function ($query) use ($retentionDays): void {
                $query
                    ->whereIn('status', [
                        MomentStatus::Expired->value,
                        MomentStatus::Deleted->value,
                    ])
                    ->where('expires_at', '<=', now()->subDays($retentionDays));
            })
            ->delete();

        $this->info('Expired Orbit Moments processed.');

        return self::SUCCESS;
    }
}
