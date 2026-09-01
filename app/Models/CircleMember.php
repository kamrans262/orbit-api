<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircleMember extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'circle_id',
        'user_id',
        'role',
        'location_mode',
        'can_ping',
        'can_message',
        'can_view_moments',
        'activity_visibility',
        'joined_at',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CircleRole::class,
            'location_mode' => LocationMode::class,
            'can_ping' => 'boolean',
            'can_message' => 'boolean',
            'can_view_moments' => 'boolean',
            'activity_visibility' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }
}
