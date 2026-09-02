<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AdCampaign extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'advertiser_id', 'name', 'status', 'placement', 'starts_at', 'ends_at', 'targeting', 'impression_cap_per_user', 'budget_minor', 'currency', 'priority', 'created_by_admin_id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'targeting' => 'array', 'impression_cap_per_user' => 'integer', 'budget_minor' => 'integer', 'priority' => 'integer', 'created_by_admin_id' => 'integer'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
