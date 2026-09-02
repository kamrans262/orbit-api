<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class FeatureFlag extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'feature_flags';

    protected $fillable = ['key', 'name', 'description', 'environment', 'status', 'default_enabled', 'rollout_percentage', 'targeting', 'starts_at', 'ends_at', 'removal_at', 'archived_at', 'owner_admin_id', 'updated_by_admin_id'];

    protected function casts(): array
    {
        return ['default_enabled' => 'boolean', 'rollout_percentage' => 'integer', 'targeting' => 'array', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'removal_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
