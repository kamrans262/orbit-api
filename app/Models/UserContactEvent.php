<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

final class UserContactEvent extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'channel', 'kind', 'direction', 'subject',
        'summary', 'source_type', 'source_id', 'actor_admin_id',
        'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $event): void {
            $event->id ??= (string) Str::uuid7();
            $event->occurred_at ??= now();
        });

        self::updating(fn (): never => throw new LogicException('User contact history is immutable.'));
        self::deleting(fn (): never => throw new LogicException('User contact history is immutable.'));
    }
}
