<?php

declare(strict_types=1);

namespace App\Modules\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Profile\Http\Resources\ProfileResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ProfileResource::make($request->user())->resolve($request),
        );
    }
}
