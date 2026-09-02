<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AdminSavedView extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_user_id', 'name', 'module', 'scope', 'filters', 'columns', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'admin_user_id' => 'integer',
            'filters' => 'array',
            'columns' => 'array',
            'sort' => 'array',
        ];
    }
}
