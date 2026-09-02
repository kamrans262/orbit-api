<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CommunicationCampaign extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'channel',
        'category',
        'priority',
        'status',
        'template_id',
        'locale',
        'subject',
        'title',
        'body',
        'deep_link',
        'audience',
        'is_emergency',
        'scheduled_at',
        'sent_at',
        'cancelled_at',
        'stats',
        'created_by_admin_id',
        'approved_by_admin_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'is_emergency' => 'boolean',
            'scheduled_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'stats' => 'array',
            'approved_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
