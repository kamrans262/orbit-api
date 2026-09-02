<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class MaintenanceWindow extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'environment',
        'service',
        'status',
        'read_only',
        'title',
        'message',
        'expected_restoration',
        'starts_at',
        'ends_at',
        'created_by_admin_id',
        'activated_by_admin_id',
        'activated_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'read_only' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
