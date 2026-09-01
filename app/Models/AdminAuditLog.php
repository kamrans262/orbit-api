<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class AdminAuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'admin_user_id', 'admin_session_id', 'action', 'target_type', 'target_id',
        'result', 'reason', 'request_id', 'ip_hash', 'user_agent_hash', 'before_state',
        'after_state', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Admin audit records are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Admin audit records are immutable.'));
    }
}
