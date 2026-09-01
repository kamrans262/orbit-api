<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\MediaAsset;
use App\Models\MediaKeyEnvelope;
use App\Modules\Media\Enums\MediaAssetStatus;
use App\Modules\Media\Exceptions\MediaException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetMediaKeyEnvelopeController extends Controller
{
    public function __invoke(Request $request, string $assetId): JsonResponse
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

        $envelope = MediaKeyEnvelope::query()
            ->where('media_asset_id', $asset->id)
            ->where('recipient_device_id', $device->id)
            ->first();

        if ($envelope === null) {
            throw MediaException::envelopeNotFound();
        }

        return ApiResponse::success(
            data: [
                'asset_id' => $asset->id,
                'recipient_device_id' => $device->id,
                'algorithm' => $envelope->algorithm,
                'encrypted_key' => $envelope->encrypted_key,
            ],
            message: 'Encrypted media key envelope retrieved.',
        );
    }
}
