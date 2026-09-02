<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CommunicationDelivery extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'campaign_id',
        'user_id',
        'channel',
        'status',
        'provider',
        'provider_reference',
        'failure_code',
        'queued_at',
        'delivered_at',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'queued_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
