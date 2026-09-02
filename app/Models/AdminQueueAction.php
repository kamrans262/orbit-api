<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AdminQueueAction extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'admin_queue_actions';

    protected $fillable = ['failed_job_uuid', 'action', 'status', 'reason', 'admin_user_id', 'result_message', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
