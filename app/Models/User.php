<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'timezone',
        'locale',
        'global_ghost_mode',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return HasMany<CircleMember, $this>
     */
    public function circleMemberships(): HasMany
    {
        return $this->hasMany(CircleMember::class);
    }

    /**
     * @return HasMany<Circle, $this>
     */
    public function createdCircles(): HasMany
    {
        return $this->hasMany(Circle::class, 'created_by');
    }

    /**
     * @return HasMany<CircleInvite, $this>
     */
    public function createdCircleInvites(): HasMany
    {
        return $this->hasMany(CircleInvite::class, 'created_by');
    }

    /**
     * @return HasOne<PresenceState, $this>
     */
    public function presenceState(): HasOne
    {
        return $this->hasOne(PresenceState::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'global_ghost_mode' => 'boolean',
        ];
    }
}
