<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminDeviceControl extends Model
{
    protected $primaryKey = 'device_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['device_id', 'suspicious', 'require_verification', 'enforcement_revoked', 'reason', 'updated_by_admin_id'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    protected function casts(): array
    {
        return ['suspicious' => 'boolean', 'require_verification' => 'boolean', 'enforcement_revoked' => 'boolean'];
    }
}
