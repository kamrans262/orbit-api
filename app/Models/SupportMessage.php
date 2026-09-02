<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SupportMessage extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'support_ticket_id', 'actor_type', 'actor_user_id',
        'actor_admin_id', 'body', 'attachment_refs', 'internal', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attachment_refs' => 'array',
            'internal' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $message): void {
            $message->id ??= (string) Str::uuid7();
            $message->created_at ??= now();
        });
    }
}
