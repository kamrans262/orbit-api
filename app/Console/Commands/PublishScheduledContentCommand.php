<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminUser;
use App\Models\ContentItem;
use App\Modules\Admin\CommunicationsContent\Services\PublicationService;
use Illuminate\Console\Command;

final class PublishScheduledContentCommand extends Command
{
    protected $signature = 'orbit:content:publish-scheduled';

    protected $description = 'Publish due reviewed CMS content scheduled by Orbit administrators.';

    public function handle(PublicationService $service): int
    {
        $processed = 0;

        ContentItem::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->chunkById(50, function ($items) use ($service, &$processed): void {
                foreach ($items as $item) {
                    $actor = $item->created_by_admin_id
                        ? AdminUser::query()->find($item->created_by_admin_id)
                        : null;
                    $service->publish('content', $item->id, $actor);
                    $processed++;
                }
            });

        $this->info("Published {$processed} scheduled content item(s).");

        return self::SUCCESS;
    }
}
