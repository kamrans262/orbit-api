<?php

declare(strict_types=1);

namespace App\Modules\Sos\Values;

use App\Models\SosEvent;

final readonly class ActivateSosResult
{
    public function __construct(
        public SosEvent $event,
        public bool $created,
    ) {}
}
