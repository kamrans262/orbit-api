<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Moments\Enums\MomentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Moment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'circle_id',
        'author_user_id',
        'media_asset_id',
        'status',
        'expires_at',
        'deleted_at',
    ];

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(MomentView::class);
    }

    public function isActive(): bool
    {
        return $this->status === MomentStatus::Active
            && $this->deleted_at === null
            && $this->expires_at->isFuture();
    }

    protected function casts(): array
    {
        return [
            'status' => MomentStatus::class,
            'expires_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
