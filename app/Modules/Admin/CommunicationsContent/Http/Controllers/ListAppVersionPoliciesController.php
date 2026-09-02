<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\AppVersionPolicy;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAppVersionPoliciesController
{
    public function __invoke(Request $request): JsonResponse
    {
        return AdminApiResponse::success($request, AppVersionPolicy::query()->orderBy('platform')->orderBy('environment')->get()->toArray());
    }
}
