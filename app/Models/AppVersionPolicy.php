<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AppVersionPolicy extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'platform',
        'environment',
        'minimum_supported_version',
        'recommended_version',
        'latest_version',
        'update_url',
        'soft_update_message',
        'forced_update_message',
        'updated_by_admin_id',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
