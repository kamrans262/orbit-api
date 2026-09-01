<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageDeliveryReceipt extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'envelope_id',
        'message_id',
        'recipient_user_id',
        'recipient_device_id',
        'delivered_at',
    ];

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['delivered_at' => 'datetime'];
    }
}
