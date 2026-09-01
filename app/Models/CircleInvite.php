<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircleInvite extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'circle_id',
        'created_by',
        'code_hash',
        'max_uses',
        'uses_count',
        'expires_at',
        'revoked_at',
    ];

    /**
     * @return BelongsTo<Circle, $this>
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $this->uses_count < $this->max_uses;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
        ];
    }
}
