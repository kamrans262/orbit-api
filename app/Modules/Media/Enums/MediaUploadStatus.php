<?php

declare(strict_types=1);

namespace App\Modules\Media\Enums;

enum MediaUploadStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Expired = 'expired';
}
