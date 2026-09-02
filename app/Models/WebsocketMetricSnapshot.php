<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class WebsocketMetricSnapshot extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'websocket_metric_snapshots';

    protected $fillable = ['environment', 'connections', 'subscriptions', 'connect_rate', 'disconnect_rate', 'reconnect_rate', 'fanout_lag_ms', 'regions', 'captured_at', 'recorded_by_admin_id'];

    protected function casts(): array
    {
        return ['connections' => 'integer', 'subscriptions' => 'integer', 'connect_rate' => 'integer', 'disconnect_rate' => 'integer', 'reconnect_rate' => 'integer', 'fanout_lag_ms' => 'integer', 'regions' => 'array', 'captured_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
