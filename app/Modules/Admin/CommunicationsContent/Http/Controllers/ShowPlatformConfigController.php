<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\UserRegionalProfile;
use App\Modules\Admin\CommunicationsContent\Services\RegionalPlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowPlatformConfigController
{
    public function __invoke(Request $request, RegionalPlatformService $service): JsonResponse
    {
        $profile = $request->user()?->getKey() ? UserRegionalProfile::query()->where('user_id', $request->user()->getKey())->first() : null;
        $country = (string) $request->query('country', $profile?->country_code ?? '');
        $platform = (string) $request->query('platform', $profile?->platform ?? '');
        $version = (string) $request->query('app_version', $profile?->app_version ?? '');

        return response()->json(['data' => $service->publicConfig($country ?: null, $platform ?: null, $version ?: null, app()->environment())]);
    }
}
