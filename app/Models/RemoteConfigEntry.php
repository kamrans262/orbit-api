<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class RemoteConfigEntry extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'remote_config_entries';

    protected $fillable = ['key', 'environment', 'status', 'critical', 'value', 'description', 'updated_by_admin_id'];

    protected function casts(): array
    {
        return ['critical' => 'boolean', 'value' => 'array'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
