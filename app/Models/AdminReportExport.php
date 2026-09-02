<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class AdminReportExport extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'admin_report_exports';

    protected $fillable = ['saved_report_id', 'admin_user_id', 'format', 'status', 'storage_path', 'row_count', 'expires_at', 'downloaded_at'];

    protected function casts(): array
    {
        return ['row_count' => 'integer', 'expires_at' => 'immutable_datetime', 'downloaded_at' => 'immutable_datetime'];
    }

    public function savedReport(): BelongsTo
    {
        return $this->belongsTo(AdminSavedReport::class, 'saved_report_id');
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
