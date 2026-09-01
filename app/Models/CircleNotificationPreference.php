<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CircleNotificationPreference extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'user_id', 'circle_id', 'muted_until', 'silent'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'muted_until' => 'immutable_datetime',
            'silent' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $preference): void {
            $preference->id ??= (string) Str::uuid7();
        });
    }
}
