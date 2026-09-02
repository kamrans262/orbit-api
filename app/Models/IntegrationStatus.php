<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class IntegrationStatus extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'integration_statuses';

    protected $fillable = ['provider', 'service', 'environment', 'enabled', 'health', 'public_config', 'last_success_at', 'last_failure_at', 'last_error', 'updated_by_admin_id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'public_config' => 'array', 'last_success_at' => 'immutable_datetime', 'last_failure_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
