<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\RegionalConfiguration;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class UpsertRegionController
{
    public function __invoke(Request $request, string $countryCode, AdminAuditLogger $audit): JsonResponse
    {
        $d = $request->validate(['status' => ['nullable', Rule::in(['active', 'disabled'])], 'feature_availability' => ['nullable', 'array'], 'subscription_availability' => ['nullable', 'array'], 'pricing' => ['nullable', 'array'], 'legal_disclosures' => ['nullable', 'array'], 'sms_available' => ['nullable', 'boolean'], 'emergency_information' => ['nullable', 'array'], 'consent_requirements' => ['nullable', 'array'], 'retention_rules' => ['nullable', 'array']]);
        $code = strtoupper($countryCode);
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return AdminApiResponse::error($request, 'Country code must be ISO alpha-2.', 'REGION_COUNTRY_INVALID', 422);
        } $before = RegionalConfiguration::query()->where('country_code', $code)->first()?->toArray() ?? [];
        $m = RegionalConfiguration::query()->updateOrCreate(['country_code' => $code], [...$d, 'status' => $d['status'] ?? 'active', 'updated_by_admin_id' => $request->user()->id]);
        $audit->write('regions.configuration.updated', $request->user(), $request->attributes->get('admin_session'), 'regional_configuration', $m->id, before: $before, after: $m->only(['country_code', 'status', 'sms_available']), request: $request);

        return AdminApiResponse::success($request,$m->toArray());
    }
}
