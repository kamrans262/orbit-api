<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Modules\Admin\CommunicationsContent\Services\RegionalPlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class UpdateConsumerRegionalProfileController
{
    public function __invoke(Request $request, RegionalPlatformService $service): JsonResponse
    {
        $d = $request->validate(['country_code' => ['nullable', 'string', 'size:2'], 'platform' => ['nullable', Rule::in(['ios', 'android', 'web'])], 'app_version' => ['nullable', 'string', 'max:50'], 'locale' => ['nullable', 'string', 'max:12']]);
        $m = $service->updateProfile($request->user(), $d);

        return response()->json(['data' => $m->only(['country_code', 'platform', 'app_version', 'locale'])]);
    }
}
