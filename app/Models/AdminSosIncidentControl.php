<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminSosIncidentControl extends Model
{
    protected $primaryKey = 'sos_event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sos_event_id',
        'assigned_admin_id',
        'operational_status',
        'internal_escalation_level',
        'false_alarm',
        'technical_failure',
        'abuse_flag',
        'operational_resolution',
        'updated_by_admin_id',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SosEvent::class, 'sos_event_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_admin_id');
    }

    protected function casts(): array
    {
        return [
            'false_alarm' => 'boolean',
            'technical_failure' => 'boolean',
            'abuse_flag' => 'boolean',
        ];
    }
}
