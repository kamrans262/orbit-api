<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Models\MediaUpload;
use App\Modules\Media\Enums\MediaAssetStatus;
use App\Modules\Media\Enums\MediaUploadStatus;
use App\Modules\Media\Services\EncryptedMediaStorage;
use Illuminate\Console\Command;

final class PurgeStaleMediaCommand extends Command
{
    protected $signature = 'orbit:media:purge-stale';

    protected $description = 'Purge expired encrypted media uploads and expired assets.';

    public function handle(EncryptedMediaStorage $storage): int
    {
        MediaUpload::query()
            ->where('status', MediaUploadStatus::Pending)
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($uploads) use ($storage): void {
                foreach ($uploads as $upload) {
                    $storage->deleteUploadChunks($upload);
                    $upload->forceFill(['status' => MediaUploadStatus::Expired])->save();
                    $upload->chunks()->delete();
                }
            });

        MediaAsset::query()
            ->where('status', MediaAssetStatus::Ready)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($assets) use ($storage): void {
                foreach ($assets as $asset) {
                    $storage->deleteAsset($asset->storage_path);
                    $asset->keyEnvelopes()->delete();
                    $asset->forceFill([
                        'status' => MediaAssetStatus::Deleted,
                        'deleted_at' => now(),
                    ])->save();
                }
            });

        $this->info('Stale Orbit media purged.');

        return self::SUCCESS;
    }
}
