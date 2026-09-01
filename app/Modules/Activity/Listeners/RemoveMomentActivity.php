<?php

declare(strict_types=1);

namespace App\Modules\Activity\Listeners;

use App\Models\Moment;
use App\Modules\Activity\Actions\RemoveActivitySourceAction;
use App\Modules\Moments\Events\MomentDeleted;

final readonly class RemoveMomentActivity
{
    public function __construct(private RemoveActivitySourceAction $remove) {}

    public function handle(MomentDeleted $event): void
    {
        $momentId = $this->momentId($event);

        if ($momentId !== '') {
            $this->remove->handle('moment', $momentId);
        }
    }

    private function momentId(MomentDeleted $event): string
    {
        if (property_exists($event, 'moment') && $event->moment instanceof Moment) {
            return (string) $event->moment->getKey();
        }

        foreach (['momentId', 'moment_id', 'id'] as $property) {
            if (property_exists($event, $property)) {
                $value = $event->{$property};

                if (is_string($value) || is_int($value)) {
                    return (string) $value;
                }
            }
        }

        if (property_exists($event, 'realtime') && is_array($event->realtime)) {
            return (string) data_get($event->realtime, 'payload.moment_id', '');
        }

        return '';
    }
}
