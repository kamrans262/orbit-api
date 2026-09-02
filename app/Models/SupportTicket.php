<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SupportTicket extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'category', 'priority', 'status', 'subject',
        'assigned_admin_id', 'sla_due_at', 'last_message_at',
        'escalated_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $ticket): void {
            $ticket->id ??= (string) Str::uuid7();
        });
    }
}
