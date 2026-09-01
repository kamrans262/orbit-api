<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminSosNote extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'sos_event_id',
        'admin_user_id',
        'note',
        'created_at',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SosEvent::class, 'sos_event_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
