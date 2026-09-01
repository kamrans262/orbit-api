<?php

declare(strict_types=1);

namespace App\Modules\Circles\Enums;

enum CircleType: string
{
    case Standard = 'standard';
    case Temporary = 'temporary';
}
