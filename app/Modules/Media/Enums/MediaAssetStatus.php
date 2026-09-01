<?php

declare(strict_types=1);

namespace App\Modules\Media\Enums;

enum MediaAssetStatus: string
{
    case Ready = 'ready';
    case Deleted = 'deleted';
}
