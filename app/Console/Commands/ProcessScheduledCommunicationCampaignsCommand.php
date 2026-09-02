<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CommunicationCampaign;
use App\Modules\Admin\CommunicationsContent\Services\CampaignService;
use Illuminate\Console\Command;

final class ProcessScheduledCommunicationCampaignsCommand extends Command
{
    protected $signature = 'orbit:communications:process-scheduled';

    protected $description = 'Dispatch due Orbit communication campaigns through their configured delivery boundaries.';

    public function handle(CampaignService $service): int
    {
        $processed = 0;
        CommunicationCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->chunkById(50, function ($campaigns) use ($service, &$processed): void {
                foreach ($campaigns as $campaign) {
                    $service->send($campaign);
                    $processed++;
                }
            });

        $this->info("Processed {$processed} scheduled communication campaign(s).");

        return self::SUCCESS;
    }
}
