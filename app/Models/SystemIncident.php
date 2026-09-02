<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SystemIncident extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'system_incidents';

    protected $fillable = ['title', 'service', 'severity', 'status', 'impact', 'assigned_admin_id', 'started_at', 'resolved_at', 'resolution', 'external_reference', 'created_by_admin_id'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
