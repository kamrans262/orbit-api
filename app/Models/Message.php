<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Messaging\Enums\MessageType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'id',
        'circle_id',
        'sender_user_id',
        'sender_device_id',
        'type',
        'client_sent_at',
        'expires_at',
    ];

    /** @return BelongsTo<Circle, $this> */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /** @return BelongsTo<Device, $this> */
    public function senderDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'sender_device_id');
    }

    /** @return HasMany<MessageEnvelope, $this> */
    public function envelopes(): HasMany
    {
        return $this->hasMany(MessageEnvelope::class);
    }

    /** @return HasMany<MessageReadReceipt, $this> */
    public function readReceipts(): HasMany
    {
        return $this->hasMany(MessageReadReceipt::class);
    }

    /** @return HasMany<MessageDeliveryReceipt, $this> */
    public function deliveryReceipts(): HasMany
    {
        return $this->hasMany(MessageDeliveryReceipt::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'client_sent_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
