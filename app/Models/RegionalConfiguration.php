<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class RegionalConfiguration extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'country_code',
        'status',
        'feature_availability',
        'subscription_availability',
        'pricing',
        'legal_disclosures',
        'sms_available',
        'emergency_information',
        'consent_requirements',
        'retention_rules',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'feature_availability' => 'array',
            'subscription_availability' => 'array',
            'pricing' => 'array',
            'legal_disclosures' => 'array',
            'sms_available' => 'boolean',
            'emergency_information' => 'array',
            'consent_requirements' => 'array',
            'retention_rules' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->id ??= (string) Str::uuid7();
        });
    }
}
