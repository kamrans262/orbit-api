<?php

declare(strict_types=1);

namespace App\Modules\Moments\Actions;

use App\Models\MediaAsset;
use App\Models\Moment;
use App\Models\User;
use App\Modules\Media\Enums\MediaAssetStatus;
use App\Modules\Media\Enums\MediaKind;
use App\Modules\Moments\Enums\MomentStatus;
use App\Modules\Moments\Events\MomentPublished;
use App\Modules\Moments\Exceptions\MomentException;
use App\Modules\Moments\Services\MomentAccess;
use Illuminate\Support\Facades\DB;

final class PublishMomentAction
{
    public function __construct(private readonly MomentAccess $access) {}

    public function handle(
        User $user,
        string $circleId,
        string $momentId,
        string $mediaAssetId,
        ?int $ttlSeconds,
    ): Moment {
        $this->access->publisher($user, $circleId);

        $existing = Moment::query()->whereKey($momentId)->first();

        if ($existing !== null) {
            if (
                $existing->author_user_id === $user->id
                && $existing->circle_id === $circleId
                && $existing->media_asset_id === $mediaAssetId
            ) {
                return $existing->load(['author', 'mediaAsset'])->loadCount('views');
            }

            throw MomentException::idConflict();
        }

        $media = MediaAsset::query()
            ->whereKey($mediaAssetId)
            ->where('circle_id', $circleId)
            ->where('uploader_user_id', $user->id)
            ->where('status', MediaAssetStatus::Ready)
            ->whereNull('deleted_at')
            ->first();

        if (
            $media === null
            || ! in_array($media->kind, [MediaKind::Image, MediaKind::Video], true)
        ) {
            throw MomentException::invalidMedia();
        }

        $maxTtl = max(300, (int) config('orbit_moments.max_ttl_seconds', 86400));
        $defaultTtl = min(
            $maxTtl,
            max(300, (int) config('orbit_moments.default_ttl_seconds', 86400)),
        );
        $ttl = min($maxTtl, max(300, $ttlSeconds ?? $defaultTtl));

        $moment = DB::transaction(fn (): Moment => Moment::query()->create([
            'id' => $momentId,
            'circle_id' => $circleId,
            'author_user_id' => $user->id,
            'media_asset_id' => $media->id,
            'status' => MomentStatus::Active,
            'expires_at' => now()->addSeconds($ttl),
        ]));

        $moment->load(['author', 'mediaAsset'])->loadCount('views');
        MomentPublished::dispatch($moment);

        return $moment;
    }
}
