<?php

declare(strict_types=1);

namespace App\Modules\Activity\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ActivityItemCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly array $realtime) {}
}
