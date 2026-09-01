<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class IdentityRefreshToken extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'session_id', 'user_id', 'device_id', 'family_id', 'token_hash', 'status',
        'replaced_by_id', 'expires_at', 'rotated_at', 'revoked_at', 'reuse_detected_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'rotated_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'reuse_detected_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $token): void {
            $token->id ??= (string) Str::uuid7();
        });
    }
}
