<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class SosResponder extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sos_event_id',
        'user_id',
        'status',
        'engaged_at',
        'responded_at',
        'last_latitude',
        'last_longitude',
        'last_location_accuracy_m',
        'last_location_at',
    ];

    protected function casts(): array
    {
        return [
            'engaged_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
            'last_location_accuracy_m' => 'float',
            'last_location_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $responder): void {
            $responder->id ??= (string) Str::uuid7();
        });
    }

    public function sosEvent(): BelongsTo
    {
        return $this->belongsTo(SosEvent::class, 'sos_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
