<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AdminIpPolicy extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_user_id', 'cidr', 'description', 'enabled', 'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'admin_user_id' => 'integer',
            'enabled' => 'boolean',
            'created_by_admin_id' => 'integer',
        ];
    }
}
