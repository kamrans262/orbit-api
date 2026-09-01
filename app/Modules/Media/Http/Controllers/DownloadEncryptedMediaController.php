<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\MediaAsset;
use App\Models\MediaKeyEnvelope;
use App\Modules\Media\Enums\MediaAssetStatus;
use App\Modules\Media\Exceptions\MediaException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadEncryptedMediaController extends Controller
{
    public function __invoke(Request $request, string $assetId): StreamedResponse
    {
        $deviceId = (string) $request->query('device_id', '');

        $device = Device::query()
            ->whereKey($deviceId)
            ->where('user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->first();

        if ($device === null) {
            throw MediaException::invalidDevice();
        }

        $asset = MediaAsset::query()
            ->whereKey($assetId)
            ->where('status', MediaAssetStatus::Ready)
            ->first();

        if ($asset === null) {
            throw MediaException::assetNotFound();
        }

        $hasEnvelope = MediaKeyEnvelope::query()
            ->where('media_asset_id', $asset->id)
            ->where('recipient_device_id', $device->id)
            ->exists();

        if (! $hasEnvelope) {
            throw MediaException::envelopeNotFound();
        }

        if (! Storage::disk($asset->storage_disk)->exists($asset->storage_path)) {
            throw MediaException::assetNotFound();
        }

        return Storage::disk($asset->storage_disk)->download(
            $asset->storage_path,
            $asset->id.'.ciphertext',
            [
                'Content-Type' => 'application/octet-stream',
                'X-Orbit-Ciphertext-SHA256' => $asset->sha256_ciphertext,
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
