<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AdminOperationalAlert extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'admin_operational_alerts';

    protected $fillable = ['kind', 'severity', 'status', 'resource_type', 'resource_id', 'title', 'message', 'metadata', 'acknowledged_at', 'acknowledged_by_admin_id'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'acknowledged_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
