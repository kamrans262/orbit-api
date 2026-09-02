<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SupportTicketLink extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'support_ticket_id', 'resource_type', 'resource_id',
        'created_by_admin_id', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $link): void {
            $link->id ??= (string) Str::uuid7();
            $link->created_at ??= now();
        });
    }
}
