<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Sos\Services\SosEscalationService;
use Illuminate\Console\Command;

final class EscalateSosCommand extends Command
{
    protected $signature = 'orbit:sos:escalate';

    protected $description = 'Advance due Orbit SOS events through server-authoritative escalation stages.';

    public function handle(SosEscalationService $service): int
    {
        $processed = $service->processDue();
        $this->info("Processed {$processed} SOS escalation transition(s).");

        return self::SUCCESS;
    }
}
