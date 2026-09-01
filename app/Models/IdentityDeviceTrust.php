<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class IdentityDeviceTrust extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'device_id', 'status', 'requested_by_device_id', 'approved_by_device_id',
        'requested_at', 'expires_at', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $trust): void {
            $trust->id ??= (string) Str::uuid7();
        });
    }
}
