<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class NotificationDelivery extends Model
{
    protected $fillable = [
        'id', 'notification_id', 'target_user_id', 'device_id', 'channel', 'provider',
        'priority', 'collapse_key', 'silent', 'payload', 'status', 'available_at',
        'dispatched_at', 'attempts',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'target_user_id' => 'integer',
            'silent' => 'boolean',
            'payload' => 'array',
            'available_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $delivery): void {
            $delivery->id ??= (string) Str::uuid7();
        });
    }
}
