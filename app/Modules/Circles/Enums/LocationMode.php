<?php

declare(strict_types=1);

namespace App\Modules\Circles\Enums;

enum LocationMode: string
{
    case Precise = 'precise';
    case Approximate = 'approximate';
    case Hidden = 'hidden';
    case Ghost = 'ghost';
}
