<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SosEvent;
use Illuminate\Console\Command;

final class PurgeExpiredSosRecordingsCommand extends Command
{
    protected $signature = 'orbit:sos:purge-expired-recordings';

    protected $description = 'Remove expired encrypted SOS recording references after the default 90-day retention window.';

    public function handle(): int
    {
        $count = SosEvent::query()
            ->whereNotNull('recording_ref')
            ->whereNotNull('recording_expires_at')
            ->where('recording_expires_at', '<=', now())
            ->update([
                'recording_ref' => null,
                'recording_expires_at' => null,
                'updated_at' => now(),
            ]);

        $this->info("Cleared {$count} expired SOS recording reference(s).");

        return self::SUCCESS;
    }
}
