<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ContentTranslation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'content_item_id',
        'locale',
        'status',
        'title',
        'body',
        'metadata',
        'reviewed_by_admin_id',
        'reviewed_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'reviewed_at' => 'immutable_datetime',
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
