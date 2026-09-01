<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminSession extends Model
{
    use HasUuids;

    protected $table = 'admin_sessions';

    protected $fillable = ['admin_user_id', 'access_token_id', 'ip_hash', 'user_agent_hash', 'last_seen_at', 'idle_expires_at', 'expires_at', 'reauthenticated_at', 'mfa_verified_at', 'revoked_at', 'revoke_reason'];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    protected function casts(): array
    {
        return ['access_token_id' => 'integer', 'last_seen_at' => 'immutable_datetime', 'idle_expires_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'reauthenticated_at' => 'immutable_datetime', 'mfa_verified_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
