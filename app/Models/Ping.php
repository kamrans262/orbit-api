<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Ping\Enums\PingResponseType;
use App\Modules\Ping\Enums\PingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ping extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'circle_id',
        'sender_membership_id',
        'recipient_membership_id',
        'status',
        'response_type',
        'expires_at',
        'responded_at',
        'dismissed_at',
    ];

    /**
     * @return BelongsTo<Circle, $this>
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /**
     * @return BelongsTo<CircleMember, $this>
     */
    public function senderMembership(): BelongsTo
    {
        return $this->belongsTo(CircleMember::class, 'sender_membership_id');
    }

    /**
     * @return BelongsTo<CircleMember, $this>
     */
    public function recipientMembership(): BelongsTo
    {
        return $this->belongsTo(CircleMember::class, 'recipient_membership_id');
    }

    public function effectiveStatus(): PingStatus
    {
        if ($this->status === PingStatus::Pending && $this->expires_at->isPast()) {
            return PingStatus::Expired;
        }

        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === PingStatus::Pending && ! $this->expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PingStatus::class,
            'response_type' => PingResponseType::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }
}
