<?php

declare(strict_types=1);

namespace App\Modules\Ping\Events;

use App\Models\Ping;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PingResponded
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Ping $ping) {}
}
