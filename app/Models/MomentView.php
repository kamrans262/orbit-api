<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomentView extends Model
{
    protected $fillable = [
        'moment_id',
        'viewer_user_id',
        'is_anonymous',
        'viewed_at',
    ];

    public function moment(): BelongsTo
    {
        return $this->belongsTo(Moment::class);
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_user_id');
    }

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'viewed_at' => 'datetime',
        ];
    }
}
