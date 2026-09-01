<?php

declare(strict_types=1);

namespace App\Modules\Media\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class MediaException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function circleNotFound(): self
    {
        return new self('Circle not found.', 'MEDIA_CIRCLE_NOT_FOUND', 404);
    }

    public static function messagingDisabled(): self
    {
        return new self('Media sharing is disabled for this Circle membership.', 'MEDIA_SHARING_DISABLED', 403);
    }

    public static function invalidDevice(): self
    {
        return new self('The selected device is invalid or revoked.', 'MEDIA_INVALID_DEVICE', 422);
    }

    public static function uploadNotFound(): self
    {
        return new self('Media upload not found.', 'MEDIA_UPLOAD_NOT_FOUND', 404);
    }

    public static function uploadExpired(): self
    {
        return new self('Media upload has expired.', 'MEDIA_UPLOAD_EXPIRED', 410);
    }

    public static function uploadCompleted(): self
    {
        return new self('Media upload is already completed.', 'MEDIA_UPLOAD_COMPLETED', 409);
    }

    public static function invalidChunk(): self
    {
        return new self('The media chunk is invalid.', 'MEDIA_INVALID_CHUNK', 422);
    }

    public static function missingChunks(): self
    {
        return new self('One or more media chunks are missing.', 'MEDIA_MISSING_CHUNKS', 422);
    }

    public static function ciphertextMismatch(): self
    {
        return new self('Encrypted media integrity check failed.', 'MEDIA_CIPHERTEXT_MISMATCH', 422);
    }

    public static function staleDeviceSet(): self
    {
        return new self('Recipient device keys changed. Refresh device keys and encrypt again.', 'MEDIA_STALE_DEVICE_SET', 409);
    }

    public static function assetNotFound(): self
    {
        return new self('Media asset not found.', 'MEDIA_ASSET_NOT_FOUND', 404);
    }

    public static function envelopeNotFound(): self
    {
        return new self('No encrypted media key exists for this device.', 'MEDIA_KEY_ENVELOPE_NOT_FOUND', 404);
    }

    public static function forbidden(): self
    {
        return new self('You do not have permission to access this media.', 'MEDIA_FORBIDDEN', 403);
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            code: $this->errorCode,
            status: $this->status,
        );
    }
}
