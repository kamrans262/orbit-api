<?php

declare(strict_types=1);

namespace App\Modules\Presence\Enums;

enum MovementType: string
{
    case Stationary = 'stationary';
    case Walking = 'walking';
    case Running = 'running';
    case Cycling = 'cycling';
    case Driving = 'driving';
    case Transit = 'transit';
    case Unknown = 'unknown';
}
