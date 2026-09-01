<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Admin\Enums\AdminStatus;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class AdminUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'status', 'totp_secret', 'mfa_confirmed_at',
        'failed_login_count', 'locked_until', 'access_expires_at', 'activated_at',
        'disabled_at', 'last_login_at', 'created_by_admin_id',
    ];

    protected $hidden = [
        'password', 'totp_secret',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_user_roles')->withTimestamps();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AdminSession::class);
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(AdminRecoveryCode::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function isOperationallyActive(): bool
    {
        return $this->status === AdminStatus::Active
            && $this->mfa_confirmed_at !== null
            && ($this->access_expires_at === null || $this->access_expires_at->isFuture());
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => AdminStatus::class,
            'totp_secret' => 'encrypted',
            'mfa_confirmed_at' => 'immutable_datetime',
            'locked_until' => 'immutable_datetime',
            'access_expires_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'failed_login_count' => 'integer',
        ];
    }
}
