<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class PaymentRefund extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'payment_transaction_id', 'user_id', 'amount_minor', 'currency', 'status', 'reason', 'internal_note', 'provider_ref', 'provider_result', 'requested_by_admin_id', 'decided_by_admin_id', 'requested_at', 'decided_at', 'completed_at'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'amount_minor' => 'integer', 'requested_by_admin_id' => 'integer', 'decided_by_admin_id' => 'integer', 'requested_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
