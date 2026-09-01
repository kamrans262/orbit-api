<?php

declare(strict_types=1);

namespace App\Modules\Media\Services;

use App\Models\MediaUpload;
use App\Models\MediaUploadChunk;
use App\Modules\Media\Exceptions\MediaException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class EncryptedMediaStorage
{
    public function disk(): string
    {
        return (string) config('orbit_media.disk', 'local');
    }

    public function storeChunk(MediaUpload $upload, int $chunkIndex, string $contents): MediaUploadChunk
    {
        $path = sprintf(
            'orbit-media/uploads/%s/chunks/%06d.part',
            $upload->id,
            $chunkIndex,
        );

        Storage::disk($this->disk())->put($path, $contents);

        return MediaUploadChunk::query()->updateOrCreate(
            [
                'media_upload_id' => $upload->id,
                'chunk_index' => $chunkIndex,
            ],
            [
                'size_bytes' => strlen($contents),
                'sha256_ciphertext' => hash('sha256', $contents),
                'storage_path' => $path,
            ],
        );
    }

    /**
     * @return array{path:string,size:int,sha256:string}
     */
    public function assemble(MediaUpload $upload): array
    {
        $chunks = $upload->chunks()->orderBy('chunk_index')->get();

        if ($chunks->count() !== $upload->total_chunks) {
            throw MediaException::missingChunks();
        }

        foreach ($chunks as $expectedIndex => $chunk) {
            if ($chunk->chunk_index !== $expectedIndex) {
                throw MediaException::missingChunks();
            }
        }

        $tempDirectory = storage_path('app/orbit-media-temp');
        if (! is_dir($tempDirectory) && ! mkdir($tempDirectory, 0775, true) && ! is_dir($tempDirectory)) {
            throw new RuntimeException('Unable to create Orbit media temp directory.');
        }

        $tempPath = $tempDirectory.'/'.$upload->id.'.ciphertext';
        $output = fopen($tempPath, 'wb');

        if ($output === false) {
            throw new RuntimeException('Unable to create Orbit media temp file.');
        }

        $hash = hash_init('sha256');
        $size = 0;

        try {
            foreach ($chunks as $chunk) {
                $input = Storage::disk($this->disk())->readStream($chunk->storage_path);

                if ($input === false) {
                    throw MediaException::missingChunks();
                }

                while (! feof($input)) {
                    $buffer = fread($input, 1024 * 1024);

                    if ($buffer === false) {
                        fclose($input);
                        throw new RuntimeException('Unable to read encrypted media chunk.');
                    }

                    if ($buffer === '') {
                        continue;
                    }

                    fwrite($output, $buffer);
                    hash_update($hash, $buffer);
                    $size += strlen($buffer);
                }

                fclose($input);
            }
        } finally {
            fclose($output);
        }

        $sha256 = hash_final($hash);

        if ($size !== $upload->expected_size_bytes || $sha256 !== strtolower($upload->expected_sha256_ciphertext)) {
            @unlink($tempPath);
            throw MediaException::ciphertextMismatch();
        }

        $assetPath = 'orbit-media/assets/'.$upload->asset_id.'/ciphertext.bin';
        $stream = fopen($tempPath, 'rb');

        if ($stream === false) {
            @unlink($tempPath);
            throw new RuntimeException('Unable to reopen Orbit media temp file.');
        }

        try {
            Storage::disk($this->disk())->writeStream($assetPath, $stream);
        } finally {
            fclose($stream);
            @unlink($tempPath);
        }

        return [
            'path' => $assetPath,
            'size' => $size,
            'sha256' => $sha256,
        ];
    }

    public function deleteUploadChunks(MediaUpload $upload): void
    {
        Storage::disk($this->disk())->deleteDirectory('orbit-media/uploads/'.$upload->id);
    }

    public function deleteAsset(string $path): void
    {
        Storage::disk($this->disk())->delete($path);
    }
}
