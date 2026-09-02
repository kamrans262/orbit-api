<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class WebhookDelivery extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'webhook_deliveries';

    protected $fillable = ['provider', 'event_type', 'provider_delivery_ref', 'endpoint_host', 'status', 'attempt_count', 'payload_hash', 'last_error', 'last_delivery_at', 'retry_requested_at'];

    protected function casts(): array
    {
        return ['attempt_count' => 'integer', 'last_delivery_at' => 'immutable_datetime', 'retry_requested_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
