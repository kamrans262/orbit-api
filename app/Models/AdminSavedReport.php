<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AdminSavedReport extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'admin_saved_reports';

    protected $fillable = ['admin_user_id', 'name', 'metrics', 'filters', 'group_by', 'comparison', 'team_shared', 'schedule', 'next_run_at', 'last_run_at'];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'filters' => 'array', 'team_shared' => 'boolean', 'next_run_at' => 'immutable_datetime', 'last_run_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
