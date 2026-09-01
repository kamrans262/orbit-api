<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Presence\Enums\MovementType;
use App\Modules\Presence\Enums\NetworkType;
use App\Modules\Presence\Enums\PresenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresenceState extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_id',
        'status',
        'latitude',
        'longitude',
        'accuracy_meters',
        'battery_level',
        'is_charging',
        'network_type',
        'movement_type',
        'location_updated_at',
        'reported_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PresenceStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'float',
            'battery_level' => 'integer',
            'is_charging' => 'boolean',
            'network_type' => NetworkType::class,
            'movement_type' => MovementType::class,
            'location_updated_at' => 'datetime',
            'reported_at' => 'datetime',
        ];
    }
}
