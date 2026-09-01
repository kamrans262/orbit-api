<?php

declare(strict_types=1);

namespace App\Modules\Circles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Circles\Actions\JoinCircleAction;
use App\Modules\Circles\Http\Requests\JoinCircleRequest;
use App\Modules\Circles\Http\Resources\CircleResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class JoinCircleController extends Controller
{
    public function __invoke(JoinCircleRequest $request, JoinCircleAction $action): JsonResponse
    {
        $membership = $action->handle($request->user(), $request->string('code')->toString());
        $membership->load('circle');
        $membership->circle->loadCount('memberships');

        return ApiResponse::success(
            data: (new CircleResource($membership->circle, $membership))->resolve($request),
            message: 'Joined Circle successfully.',
        );
    }
}
