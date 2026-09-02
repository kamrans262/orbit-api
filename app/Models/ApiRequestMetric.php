<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ApiRequestMetric extends Model
{
    public $timestamps = false;

    protected $fillable = ['request_id', 'method', 'route', 'status_code', 'latency_ms', 'is_admin', 'occurred_at'];

    protected function casts(): array
    {
        return ['status_code' => 'integer', 'latency_ms' => 'integer', 'is_admin' => 'boolean', 'occurred_at' => 'immutable_datetime'];
    }
}
