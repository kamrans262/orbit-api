<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class AdminRole extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'description', 'is_system', 'created_by_admin_id'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, 'admin_role_permissions')->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(AdminUser::class, 'admin_user_roles')->withTimestamps();
    }

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }
}
