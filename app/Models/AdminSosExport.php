<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AdminSosExport extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'sos_event_id',
        'requested_by_admin_id',
        'format',
        'status',
        'snapshot',
        'requested_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'requested_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
