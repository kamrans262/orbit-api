<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AccountDeletionRequest extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'status', 'reason', 'blocking_reason', 'requested_at', 'scheduled_for',
        'cancelled_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'immutable_datetime',
            'scheduled_for' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $request): void {
            $request->id ??= (string) Str::uuid7();
        });
    }
}
