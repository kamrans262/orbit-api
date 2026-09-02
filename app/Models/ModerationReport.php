<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ModerationReport extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_admin_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ModerationCaseNote::class, 'report_id');
    }

    public function enforcements(): HasMany
    {
        return $this->hasMany(ModerationEnforcement::class, 'report_id');
    }

    protected function casts(): array
    {
        return [
            'evidence' => 'array', 'target_snapshot' => 'array', 'risk_score' => 'integer',
            'triaged_at' => 'immutable_datetime', 'review_started_at' => 'immutable_datetime',
            'actioned_at' => 'immutable_datetime', 'escalated_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime',
        ];
    }
}
