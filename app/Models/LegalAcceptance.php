<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class LegalAcceptance extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'legal_document_id',
        'user_id',
        'accepted_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
