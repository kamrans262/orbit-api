<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ActivityHiddenEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'activity_event_id',
        'hidden_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'hidden_at' => 'immutable_datetime',
        ];
    }
}
