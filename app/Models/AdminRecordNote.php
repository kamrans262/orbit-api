<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AdminRecordNote extends Model
{
    use HasUuids;

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = ['admin_user_id', 'target_type', 'target_id', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
