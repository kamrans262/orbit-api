<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ModerationAppeal extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function enforcement(): BelongsTo
    {
        return $this->belongsTo(ModerationEnforcement::class, 'enforcement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_admin_id');
    }

    protected function casts(): array
    {
        return ['requires_second_review' => 'boolean', 'review_metadata' => 'array', 'submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'second_reviewed_at' => 'immutable_datetime'];
    }
}
