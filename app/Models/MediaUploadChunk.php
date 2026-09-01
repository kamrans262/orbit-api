<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaUploadChunk extends Model
{
    protected $fillable = [
        'media_upload_id',
        'chunk_index',
        'size_bytes',
        'sha256_ciphertext',
        'storage_path',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(MediaUpload::class, 'media_upload_id');
    }

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'size_bytes' => 'integer',
        ];
    }
}
