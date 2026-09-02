<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SystemIncidentNote extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'system_incident_notes';

    protected $fillable = ['incident_id', 'admin_user_id', 'note'];

    protected function casts(): array
    {
        return [];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
