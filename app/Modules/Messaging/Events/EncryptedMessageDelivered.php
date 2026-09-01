<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class EncryptedMessageDelivered
{
    use Dispatchable;

    public function __construct(
        public readonly string $messageId,
        public readonly int $senderUserId,
        public readonly int $recipientUserId,
        public readonly string $recipientDeviceId,
        public readonly string $deliveredAt,
    ) {}
}
