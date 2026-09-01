<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Actions\ImportSosNotificationOutboxAction;
use App\Modules\Notifications\Services\NotificationEventPayloadExtractor;

final readonly class ImportSosNotifications
{
    public function __construct(
        private ImportSosNotificationOutboxAction $importer,
        private NotificationEventPayloadExtractor $extractor,
    ) {}

    public function handle(object $event): void
    {
        $payload = $this->extractor->payload($event);
        $sosId = $this->extractor->first($payload, ['sos_id']);
        $this->importer->handle($sosId !== null ? (string) $sosId : null);
    }
}
