<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class UserSubscription extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'user_id', 'plan_id', 'status', 'source', 'provider', 'provider_subscription_ref', 'price_amount_minor', 'price_currency', 'billing_interval', 'complimentary', 'promotion_id', 'created_by_admin_id', 'started_at', 'current_period_end', 'cancel_at', 'cancelled_at', 'ends_at'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'price_amount_minor' => 'integer', 'complimentary' => 'boolean', 'created_by_admin_id' => 'integer', 'started_at' => 'immutable_datetime', 'current_period_end' => 'immutable_datetime', 'cancel_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
