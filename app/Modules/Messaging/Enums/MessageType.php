<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Media = 'media';
    case Voice = 'voice';
}
