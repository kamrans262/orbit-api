<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AdminDashboardPreference extends Model
{
    protected $primaryKey = 'admin_user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['admin_user_id', 'layout'];

    protected function casts(): array
    {
        return [
            'admin_user_id' => 'integer',
            'layout' => 'array',
        ];
    }
}
