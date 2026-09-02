<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserSubscription;
use Illuminate\Console\Command;

final class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'orbit:billing:expire-subscriptions';

    protected $description = 'Expire subscriptions whose configured end or cancellation time has passed.';

    public function handle(): int
    {
        $count = UserSubscription::query()->whereIn('status', ['active', 'cancel_pending'])->where(function ($q) {
            $q->where(fn ($x) => $x->whereNotNull('ends_at')->where('ends_at', '<=', now()))->orWhere(fn ($x) => $x->whereNotNull('cancel_at')->where('cancel_at', '<=', now()));
        })->update(['status' => 'expired']);
        $this->info("Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}
