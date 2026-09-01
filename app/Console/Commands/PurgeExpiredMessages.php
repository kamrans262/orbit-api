<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

final class PurgeExpiredMessages extends Command
{
    protected $signature = 'orbit:messages:purge-expired';

    protected $description = 'Purge expired encrypted messaging routing records and undelivered envelopes.';

    public function handle(): int
    {
        $count = Message::query()->where('expires_at', '<=', now())->delete();

        $this->info("Purged {$count} expired message record(s).");

        return self::SUCCESS;
    }
}
