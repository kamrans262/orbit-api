<?php

declare(strict_types=1);

namespace App\Modules\Media\Actions;

use App\Models\MediaAsset;
use App\Models\MediaKeyEnvelope;
use App\Models\MediaUpload;
use App\Models\User;
use App\Modules\Media\Enums\MediaAssetStatus;
use App\Modules\Media\Enums\MediaUploadStatus;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Services\EncryptedMediaStorage;
use App\Modules\Media\Services\RecipientDeviceSet;
use Illuminate\Support\Facades\DB;

final class CompleteMediaUploadAction
{
    public function __construct(
        private readonly EncryptedMediaStorage $storage,
        private readonly RecipientDeviceSet $recipientDevices,
    ) {}

    /**
     * @param  array<int, array{recipient_device_id:string, algorithm:string, encrypted_key:string}>  $envelopes
     */
    public function handle(User $user, string $uploadId, array $envelopes): MediaAsset
    {
        $upload = MediaUpload::query()
            ->with('chunks')
            ->whereKey($uploadId)
            ->where('uploader_user_id', $user->id)
            ->first();

        if ($upload === null) {
            throw MediaException::uploadNotFound();
        }

        if ($upload->status === MediaUploadStatus::Completed) {
            $existing = MediaAsset::query()->find($upload->asset_id);

            return $existing ?? throw MediaException::assetNotFound();
        }

        if ($upload->expires_at->isPast()) {
            $upload->forceFill(['status' => MediaUploadStatus::Expired])->save();
            throw MediaException::uploadExpired();
        }

        if (! $this->recipientDevices->matchesCurrentSet($upload->circle_id, $envelopes)) {
            throw MediaException::staleDeviceSet();
        }

        $assembled = $this->storage->assemble($upload);

        try {
            $asset = DB::transaction(function () use ($upload, $envelopes, $assembled): MediaAsset {
                $asset = MediaAsset::query()->create([
                    'id' => $upload->asset_id,
                    'circle_id' => $upload->circle_id,
                    'uploader_user_id' => $upload->uploader_user_id,
                    'uploader_device_id' => $upload->uploader_device_id,
                    'kind' => $upload->kind,
                    'content_type_hint' => $upload->content_type_hint,
                    'storage_disk' => $this->storage->disk(),
                    'storage_path' => $assembled['path'],
                    'size_bytes' => $assembled['size'],
                    'sha256_ciphertext' => $assembled['sha256'],
                    'status' => MediaAssetStatus::Ready,
                    'expires_at' => now()->addDays(
                        max(1, (int) config('orbit_media.asset_retention_days', 30)),
                    ),
                ]);

                foreach ($envelopes as $envelope) {
                    MediaKeyEnvelope::query()->create([
                        'media_asset_id' => $asset->id,
                        'recipient_device_id' => $envelope['recipient_device_id'],
                        'algorithm' => $envelope['algorithm'],
                        'encrypted_key' => $envelope['encrypted_key'],
                    ]);
                }

                $upload->forceFill([
                    'status' => MediaUploadStatus::Completed,
                    'completed_at' => now(),
                ])->save();

                return $asset;
            });
        } catch (\Throwable $exception) {
            $this->storage->deleteAsset($assembled['path']);
            throw $exception;
        }

        $this->storage->deleteUploadChunks($upload);

        return $asset->load('keyEnvelopes');
    }
}
