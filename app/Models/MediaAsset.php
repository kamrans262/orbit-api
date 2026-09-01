<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Media\Enums\MediaAssetStatus;
use App\Modules\Media\Enums\MediaKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaAsset extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'circle_id',
        'uploader_user_id',
        'uploader_device_id',
        'kind',
        'content_type_hint',
        'storage_disk',
        'storage_path',
        'size_bytes',
        'sha256_ciphertext',
        'status',
        'expires_at',
        'deleted_at',
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

    public function keyEnvelopes(): HasMany
    {
        return $this->hasMany(MediaKeyEnvelope::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'status' => MediaAssetStatus::class,
            'size_bytes' => 'integer',
            'expires_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
