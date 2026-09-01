<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminUserControl extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id', 'status', 'suspended_until', 'suspension_reason', 'feature_restrictions',
        'rate_limit_per_minute', 'require_reverification', 'risk_level', 'warning',
        'trust_safety_escalated_at', 'updated_by_admin_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'suspended_until' => 'immutable_datetime',
            'feature_restrictions' => 'array',
            'rate_limit_per_minute' => 'integer',
            'require_reverification' => 'boolean',
            'trust_safety_escalated_at' => 'immutable_datetime',
        ];
    }
}
