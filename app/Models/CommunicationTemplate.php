<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CommunicationTemplate extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'channel',
        'category',
        'status',
        'variables',
        'created_by_admin_id',
        'published_by_admin_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
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
