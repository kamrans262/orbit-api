<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class LegalDocument extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'document_type',
        'version',
        'status',
        'regions',
        'requires_reacceptance',
        'effective_at',
        'published_at',
        'created_by_admin_id',
        'published_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'regions' => 'array',
            'requires_reacceptance' => 'boolean',
            'effective_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
