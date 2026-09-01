<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class EncryptedMessageAccepted
{
    use Dispatchable;

    public function __construct(public readonly string $messageId) {}
}
