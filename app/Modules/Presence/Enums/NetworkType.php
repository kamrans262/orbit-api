<?php

declare(strict_types=1);

namespace App\Modules\Presence\Enums;

enum NetworkType: string
{
    case Wifi = 'wifi';
    case Cellular = 'cellular';
    case Ethernet = 'ethernet';
    case Offline = 'offline';
    case Unknown = 'unknown';
}
