<?php

declare(strict_types=1);

namespace App\Modules\Activity\Listeners;

use App\Models\Moment;
use App\Modules\Activity\Actions\RecordActivityEventAction;
use App\Modules\Activity\Enums\ActivityEventType;
use App\Modules\Moments\Events\MomentPublished;
use DateTimeInterface;

final readonly class RecordMomentPublishedActivity
{
    public function __construct(private RecordActivityEventAction $record) {}

    public function handle(MomentPublished $event): void
    {
        $moment = $event->moment;

        if (! $moment instanceof Moment) {
            return;
        }

        $circleId = (string) $moment->getAttribute('circle_id');
        $momentId = (string) $moment->getKey();

        if ($circleId === '' || $momentId === '') {
            return;
        }

        $authorUserId = $this->firstAttribute($moment, [
            'author_user_id',
            'sender_user_id',
            'sender_id',
            'user_id',
        ]);

        $safePayload = [
            'moment_id' => $momentId,
        ];

        $mediaType = $this->firstAttribute($moment, ['media_type', 'type']);

        if ($mediaType !== null && $mediaType !== '') {
            $safePayload['media_type'] = $mediaType;
        }

        $expiresAt = $moment->getAttribute('expires_at');

        if ($expiresAt instanceof DateTimeInterface) {
            $safePayload['expires_at'] = $expiresAt->format(DATE_ATOM);
        } elseif ($expiresAt !== null && $expiresAt !== '') {
            $safePayload['expires_at'] = (string) $expiresAt;
        }

        $this->record->handle(
            ActivityEventType::MomentPublished,
            $circleId,
            is_numeric($authorUserId) ? (int) $authorUserId : null,
            'moment',
            $momentId,
            'moment.published:'.$momentId,
            $safePayload,
        );
    }

    /**
     * Read only known metadata fields from the Moment model. Private/encrypted
     * content is intentionally never copied into the Activity payload.
     *
     * @param  list<string>  $keys
     */
    private function firstAttribute(Moment $moment, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $moment->getAttribute($key);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
