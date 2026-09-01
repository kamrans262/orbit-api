<?php

declare(strict_types=1);

namespace App\Modules\Ping\Enums;

enum PingResponseType: string
{
    case Hey = 'hey';
    case ShareLocation = 'share_location';
}
