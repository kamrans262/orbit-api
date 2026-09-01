<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminLoginEvent extends Model
{
    use HasUuids;

    protected $table = 'admin_login_events';

    protected $fillable = ['admin_user_id', 'email_hash', 'event_type', 'success', 'suspicious', 'ip_hash', 'user_agent_hash', 'failure_code', 'metadata', 'occurred_at'];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    protected function casts(): array
    {
        return ['success' => 'boolean', 'suspicious' => 'boolean', 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
