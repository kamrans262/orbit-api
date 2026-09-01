<?php

declare(strict_types=1);

namespace App\Modules\Activity\Enums;

enum ActivityEventType: string
{
    case MomentPublished = 'moment.published';
    case MemberJoined = 'member.joined';
    case MemberLeft = 'member.left';
    case SosActivated = 'alert.sos_activated';
    case SosEscalated = 'alert.sos_escalated';
    case SosResolved = 'alert.sos_resolved';
}
