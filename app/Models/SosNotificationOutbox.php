<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SosNotificationOutbox extends Model
{
    protected $table = 'sos_notification_outbox';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sos_event_id',
        'target_user_id',
        'channel',
        'kind',
        'priority',
        'payload',
        'status',
        'available_at',
        'delivered_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $notification): void {
            $notification->id ??= (string) Str::uuid7();
        });
    }
}
