<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class BillingPromotion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'code', 'name', 'plan_id', 'percent_off', 'amount_off_minor', 'currency', 'duration_days', 'max_redemptions', 'redemptions_count', 'status', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['percent_off' => 'integer', 'amount_off_minor' => 'integer', 'duration_days' => 'integer', 'max_redemptions' => 'integer', 'redemptions_count' => 'integer', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
