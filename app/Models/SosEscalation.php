<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class SosEscalation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sos_event_id',
        'stage',
        'action',
        'status',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
            'context' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $escalation): void {
            $escalation->id ??= (string) Str::uuid7();
        });
    }

    public function sosEvent(): BelongsTo
    {
        return $this->belongsTo(SosEvent::class, 'sos_event_id');
    }
}
