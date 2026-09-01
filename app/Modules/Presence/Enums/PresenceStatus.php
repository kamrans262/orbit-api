<?php

declare(strict_types=1);

namespace App\Modules\Presence\Enums;

enum PresenceStatus: string
{
    case Online = 'online';
    case Idle = 'idle';
    case Offline = 'offline';
}
