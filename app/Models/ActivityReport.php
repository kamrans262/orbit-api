<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ActivityReport extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'activity_event_id',
        'reason',
        'details',
        'status',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $report): void {
            $report->id ??= (string) Str::uuid7();
        });
    }
}
