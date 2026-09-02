<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class PaymentTransaction extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'user_id', 'subscription_id', 'provider', 'provider_transaction_ref', 'type', 'amount_minor', 'currency', 'status', 'failure_code', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'amount_minor' => 'integer', 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
