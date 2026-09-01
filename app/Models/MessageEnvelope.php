<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageEnvelope extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'envelope_id',
        'message_id',
        'recipient_user_id',
        'recipient_device_id',
        'ciphertext',
        'encrypted_preview',
        'expires_at',
    ];

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** @return BelongsTo<Device, $this> */
    public function recipientDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'recipient_device_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
