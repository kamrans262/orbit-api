<?php

declare(strict_types=1);

namespace App\Modules\Sos\Enums;

enum SosStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';
}
