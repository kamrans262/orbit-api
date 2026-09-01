<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminRecoveryCode extends Model
{
    protected $table = 'admin_recovery_codes';

    protected $fillable = ['admin_user_id', 'code_hash', 'used_at'];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    protected function casts(): array
    {
        return ['used_at' => 'immutable_datetime'];
    }
}
