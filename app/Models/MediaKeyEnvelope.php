<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaKeyEnvelope extends Model
{
    protected $fillable = [
        'media_asset_id',
        'recipient_device_id',
        'algorithm',
        'encrypted_key',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function recipientDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'recipient_device_id');
    }
}
