<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Circles\Actions\CreateCircleAction;
use App\Modules\Circles\Http\Requests\CreateCircleRequest;
use App\Modules\Circles\Http\Resources\CircleResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateCircleController extends Controller
{
    public function __invoke(CreateCircleRequest $request, CreateCircleAction $action): JsonResponse
    {
        $result = $action->handle($request->user(), $request->validated());
        $result['circle']->loadCount('memberships');

        return ApiResponse::success(
            data: (new CircleResource($result['circle'], $result['membership']))->resolve($request),
            message: 'Circle created successfully.',
            status: 201,
        );
    }
}
