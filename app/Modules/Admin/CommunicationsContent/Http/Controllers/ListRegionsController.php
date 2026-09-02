<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\RegionalConfiguration;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListRegionsController
{
    public function __invoke(Request $request): JsonResponse
    {
        return AdminApiResponse::success($request, RegionalConfiguration::query()->orderBy('country_code')->get()->toArray());
    }
}
