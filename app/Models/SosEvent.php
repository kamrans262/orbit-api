<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class SosEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'circle_id',
        'status',
        'escalation_stage',
        'activated_at',
        'resolved_at',
        'resolution_reason',
        'recording_ref',
        'recording_expires_at',
        'last_latitude',
        'last_longitude',
        'last_location_accuracy_m',
        'last_location_at',
    ];

    protected function casts(): array
    {
        return [
            'escalation_stage' => 'integer',
            'activated_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'recording_expires_at' => 'immutable_datetime',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
            'last_location_accuracy_m' => 'float',
            'last_location_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $event): void {
            $event->id ??= (string) Str::uuid7();
        });
    }

    public function originator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responders(): HasMany
    {
        return $this->hasMany(SosResponder::class, 'sos_event_id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(SosEscalation::class, 'sos_event_id');
    }
}
