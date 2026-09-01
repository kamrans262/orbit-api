<?php

declare(strict_types=1);

namespace App\Modules\Presence\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PresenceUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly int $userId) {}
}
