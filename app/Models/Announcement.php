<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Announcement extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'type',
        'status',
        'priority',
        'dismissible',
        'deep_link',
        'audience',
        'starts_at',
        'ends_at',
        'created_by_admin_id',
        'published_by_admin_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'dismissible' => 'boolean',
            'audience' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
