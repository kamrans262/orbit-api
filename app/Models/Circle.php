<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Circles\Enums\CircleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Circle extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'created_by',
        'name',
        'description',
        'type',
        'expires_at',
        'archived_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CircleMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CircleMember::class);
    }

    /**
     * @return HasMany<CircleInvite, $this>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(CircleInvite::class);
    }

    /**
     * @param  Builder<Circle>  $query
     * @return Builder<Circle>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->whereNull('archived_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CircleType::class,
            'expires_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
