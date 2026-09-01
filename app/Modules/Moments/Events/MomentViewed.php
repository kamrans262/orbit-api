<?php

declare(strict_types=1);

namespace App\Modules\Moments\Events;

use App\Models\Moment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MomentViewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Moment $moment,
        public readonly ?int $viewerUserId,
        public readonly bool $anonymous,
        public readonly string $viewedAt,
    ) {}
}
