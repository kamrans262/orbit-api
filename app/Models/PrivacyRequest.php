<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class PrivacyRequest extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'type', 'source', 'status', 'identity_status',
        'assigned_admin_id', 'details', 'resolution', 'deadline_at',
        'linked_data_export_id', 'linked_deletion_id', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $request): void {
            $request->id ??= (string) Str::uuid7();
        });
    }
}
