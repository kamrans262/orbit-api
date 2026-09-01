<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ActivityEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'circle_id',
        'actor_user_id',
        'event_type',
        'source_type',
        'source_id',
        'event_key',
        'payload',
        'occurred_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_user_id' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $event): void {
            $event->id ??= (string) Str::uuid7();
        });
    }
}
