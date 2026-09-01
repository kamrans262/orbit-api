<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class IdentitySession extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'device_id', 'access_token_id', 'refresh_family_id', 'status',
        'device_key_fingerprint', 'last_seen_at', 'access_expires_at', 'refresh_expires_at',
        'revoked_at', 'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
            'access_expires_at' => 'immutable_datetime',
            'refresh_expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $session): void {
            $session->id ??= (string) Str::uuid7();
        });
    }
}
