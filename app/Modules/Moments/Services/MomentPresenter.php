<?php

declare(strict_types=1);

namespace App\Modules\Moments\Services;

use App\Models\Moment;
use App\Models\User;

final class MomentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function make(Moment $moment, User $viewer): array
    {
        $moment->loadMissing(['author', 'mediaAsset']);

        $viewCount = isset($moment->views_count)
            ? (int) $moment->views_count
            : $moment->views()->count();

        return [
            'id' => $moment->id,
            'circle_id' => $moment->circle_id,
            'author' => [
                'user_id' => $moment->author->id,
                'name' => $moment->author->name,
            ],
            'media' => [
                'asset_id' => $moment->mediaAsset->id,
                'kind' => $moment->mediaAsset->kind->value,
                'content_type_hint' => $moment->mediaAsset->content_type_hint,
                'size_bytes' => $moment->mediaAsset->size_bytes,
                'sha256_ciphertext' => $moment->mediaAsset->sha256_ciphertext,
            ],
            'view_count' => $viewCount,
            'is_mine' => $moment->author_user_id === $viewer->id,
            'expires_at' => $moment->expires_at->toIso8601String(),
            'created_at' => $moment->created_at?->toIso8601String(),
        ];
    }
}
