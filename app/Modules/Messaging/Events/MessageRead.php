<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class MessageRead
{
    use Dispatchable;

    public function __construct(
        public readonly string $messageId,
        public readonly string $circleId,
        public readonly int $senderUserId,
        public readonly int $readerUserId,
        public readonly string $readAt,
    ) {}
}
