<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class AdminSosSensitiveAccess extends Model
{
    use HasUuids;

    protected $table = 'admin_sos_sensitive_access_logs';

    public $timestamps = false;

    protected $fillable = [
        'sos_event_id',
        'admin_user_id',
        'admin_session_id',
        'access_type',
        'purpose',
        'reason',
        'request_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('SOS sensitive access records are immutable.'));
        self::deleting(fn (): never => throw new LogicException('SOS sensitive access records are immutable.'));
    }
}
