<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\AppVersionPolicy;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UpsertAppVersionPolicyController
{
    public function __invoke(Request $request, string $platform, AdminAuditLogger $audit): JsonResponse
    {
        $platform = strtolower($platform);
        if (! in_array($platform, ['ios', 'android', 'web'], true)) {
            return AdminApiResponse::error($request, 'Unsupported platform.', 'APP_VERSION_PLATFORM_INVALID', 422);
        } $d = $request->validate(['environment' => ['nullable', 'string', 'max:24'], 'minimum_supported_version' => ['nullable', 'string', 'max:50'], 'recommended_version' => ['nullable', 'string', 'max:50'], 'latest_version' => ['nullable', 'string', 'max:50'], 'update_url' => ['nullable', 'url', 'max:500'], 'soft_update_message' => ['nullable', 'string', 'max:2000'], 'forced_update_message' => ['nullable', 'string', 'max:2000']]);
        $env = $d['environment'] ?? app()->environment();
        $before = AppVersionPolicy::query()->where('platform', $platform)->where('environment', $env)->first()?->toArray() ?? [];
        $m = AppVersionPolicy::query()->updateOrCreate(['platform' => $platform, 'environment' => $env], [...$d, 'updated_by_admin_id' => $request->user()->id]);
        $audit->write('app_versions.policy.updated', $request->user(), $request->attributes->get('admin_session'), 'app_version_policy', $m->id, before: $before, after: $m->only(['platform', 'environment', 'minimum_supported_version', 'recommended_version', 'latest_version']), request: $request);

        return AdminApiResponse::success($request,$m->toArray());
    }
}
