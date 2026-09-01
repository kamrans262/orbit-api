<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminCircleControl extends Model
{
    protected $primaryKey = 'circle_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'circle_id', 'status', 'feature_restrictions', 'reason', 'frozen_at', 'removed_at', 'updated_by_admin_id',
    ];

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    protected function casts(): array
    {
        return [
            'feature_restrictions' => 'array',
            'frozen_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }
}
