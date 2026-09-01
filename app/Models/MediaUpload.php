<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Media\Enums\MediaKind;
use App\Modules\Media\Enums\MediaUploadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaUpload extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'asset_id',
        'circle_id',
        'uploader_user_id',
        'uploader_device_id',
        'kind',
        'content_type_hint',
        'expected_size_bytes',
        'expected_sha256_ciphertext',
        'chunk_size_bytes',
        'total_chunks',
        'status',
        'expires_at',
        'completed_at',
    ];

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_user_id');
    }

    public function uploaderDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'uploader_device_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(MediaUploadChunk::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'status' => MediaUploadStatus::class,
            'expected_size_bytes' => 'integer',
            'chunk_size_bytes' => 'integer',
            'total_chunks' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
