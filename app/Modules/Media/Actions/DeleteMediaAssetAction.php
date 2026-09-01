<?php

declare(strict_types=1);

namespace App\Modules\Media\Actions;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Media\Enums\MediaAssetStatus;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Services\EncryptedMediaStorage;
use Illuminate\Support\Facades\DB;

final class DeleteMediaAssetAction
{
    public function __construct(private readonly EncryptedMediaStorage $storage) {}

    public function handle(User $user, string $assetId): void
    {
        $asset = MediaAsset::query()->whereKey($assetId)->first();

        if ($asset === null || $asset->status === MediaAssetStatus::Deleted) {
            throw MediaException::assetNotFound();
        }

        if ($asset->uploader_user_id !== $user->id) {
            throw MediaException::forbidden();
        }

        DB::transaction(function () use ($asset): void {
            $asset->keyEnvelopes()->delete();
            $asset->forceFill([
                'status' => MediaAssetStatus::Deleted,
                'deleted_at' => now(),
            ])->save();
        });

        $this->storage->deleteAsset($asset->storage_path);
    }
}
