<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ModerationEnforcement extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ModerationReport::class, 'report_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(ModerationAppeal::class, 'enforcement_id');
    }

    protected function casts(): array
    {
        return ['parameters' => 'array', 'applied_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime'];
    }
}
