<?php

declare(strict_types=1);

namespace App\Modules\Media\Actions;

use App\Models\MediaUpload;
use App\Models\User;
use App\Modules\Media\Enums\MediaUploadStatus;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Services\EncryptedMediaStorage;

final class StoreMediaChunkAction
{
    public function __construct(private readonly EncryptedMediaStorage $storage) {}

    public function handle(User $user, string $uploadId, int $chunkIndex, string $contents, ?string $declaredSha256): void
    {
        $upload = MediaUpload::query()
            ->whereKey($uploadId)
            ->where('uploader_user_id', $user->id)
            ->first();

        if ($upload === null) {
            throw MediaException::uploadNotFound();
        }

        if ($upload->status !== MediaUploadStatus::Pending) {
            throw MediaException::uploadCompleted();
        }

        if ($upload->expires_at->isPast()) {
            $upload->forceFill(['status' => MediaUploadStatus::Expired])->save();
            throw MediaException::uploadExpired();
        }

        if ($chunkIndex < 0 || $chunkIndex >= $upload->total_chunks) {
            throw MediaException::invalidChunk();
        }

        $size = strlen($contents);
        $lastIndex = $upload->total_chunks - 1;
        $expectedMax = $chunkIndex === $lastIndex
            ? $upload->expected_size_bytes - ($upload->chunk_size_bytes * $lastIndex)
            : $upload->chunk_size_bytes;

        if ($size < 1 || $size !== $expectedMax) {
            throw MediaException::invalidChunk();
        }

        $actualSha256 = hash('sha256', $contents);

        if ($declaredSha256 !== null && strtolower($declaredSha256) !== $actualSha256) {
            throw MediaException::invalidChunk();
        }

        $this->storage->storeChunk($upload, $chunkIndex, $contents);
    }
}
