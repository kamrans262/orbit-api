<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class TypingIndicatorChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $circleId,
        public readonly string $membershipId,
        public readonly int $userId,
        public readonly bool $isTyping,
    ) {}
}
