<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class AdminPermission extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_sensitive'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_permissions')->withTimestamps();
    }

    protected function casts(): array
    {
        return ['is_sensitive' => 'boolean'];
    }
}
