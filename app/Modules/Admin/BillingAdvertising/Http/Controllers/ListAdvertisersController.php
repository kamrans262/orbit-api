<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\Advertiser;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListAdvertisersController
{
    public function __invoke(Request $request): JsonResponse
    {
        return AdminApiResponse::success($request, Advertiser::query()->latest()->paginate(50));
    }
}
