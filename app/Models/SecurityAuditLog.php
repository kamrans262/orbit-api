<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SecurityAuditLog extends Model
{
    protected $table = 'audit_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'actor_user_id', 'action', 'target_type', 'target_id',
        'ip_hash', 'user_agent_hash', 'metadata', 'occurred_at',
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
        self::creating(function (self $log): void {
            $log->id ??= (string) Str::uuid7();
        });
    }
}
