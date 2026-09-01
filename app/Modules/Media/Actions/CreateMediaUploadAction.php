<?php

declare(strict_types=1);

namespace App\Modules\Media\Actions;

use App\Models\Device;
use App\Models\MediaUpload;
use App\Models\User;
use App\Modules\Media\Enums\MediaUploadStatus;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Services\CircleMediaAccess;
use Illuminate\Support\Str;

final class CreateMediaUploadAction
{
    public function __construct(private readonly CircleMediaAccess $access) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, string $circleId, array $data): MediaUpload
    {
        $this->access->membership($user, $circleId, true);

        $device = Device::query()
            ->whereKey($data['uploader_device_id'])
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        if ($device === null) {
            throw MediaException::invalidDevice();
        }

        $maxSize = max(1, (int) config('orbit_media.max_size_bytes', 104857600));
        $size = (int) $data['size_bytes'];

        if ($size < 1 || $size > $maxSize) {
            throw MediaException::invalidChunk();
        }

        $chunkSize = max(262144, (int) config('orbit_media.chunk_size_bytes', 5242880));
        $totalChunks = (int) ceil($size / $chunkSize);

        return MediaUpload::query()->create([
            'id' => (string) Str::uuid(),
            'asset_id' => $data['asset_id'],
            'circle_id' => $circleId,
            'uploader_user_id' => $user->id,
            'uploader_device_id' => $device->id,
            'kind' => $data['kind'],
            'content_type_hint' => $data['content_type_hint'] ?? null,
            'expected_size_bytes' => $size,
            'expected_sha256_ciphertext' => strtolower($data['sha256_ciphertext']),
            'chunk_size_bytes' => $chunkSize,
            'total_chunks' => $totalChunks,
            'status' => MediaUploadStatus::Pending,
            'expires_at' => now()->addMinutes(
                max(5, (int) config('orbit_media.upload_ttl_minutes', 60)),
            ),
        ]);
    }
}
