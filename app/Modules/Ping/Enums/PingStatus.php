<?php

declare(strict_types=1);

namespace App\Modules\Ping\Enums;

enum PingStatus: string
{
    case Pending = 'pending';
    case Responded = 'responded';
    case Dismissed = 'dismissed';
    case Expired = 'expired';
}
