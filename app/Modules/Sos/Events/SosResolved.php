<?php

declare(strict_types=1);

namespace App\Modules\Sos\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SosResolved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly array $realtime) {}
}
