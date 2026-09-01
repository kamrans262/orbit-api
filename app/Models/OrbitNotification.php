<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class OrbitNotification extends Model
{
    protected $table = 'orbit_notifications';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'circle_id', 'kind', 'priority', 'idempotency_key', 'summary',
        'payload', 'deep_link', 'in_app_visible', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'payload' => 'array',
            'in_app_visible' => 'boolean',
            'read_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $notification): void {
            $notification->id ??= (string) Str::uuid7();
        });
    }
}
