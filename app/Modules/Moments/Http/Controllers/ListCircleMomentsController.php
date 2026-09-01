<?php

declare(strict_types=1);

namespace App\Modules\Moments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Moments\Actions\ListCircleMomentsAction;
use App\Modules\Moments\Services\MomentPresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListCircleMomentsController extends Controller
{
    public function __invoke(
        Request $request,
        string $circleId,
        ListCircleMomentsAction $action,
        MomentPresenter $presenter,
    ): JsonResponse {
        $moments = $action->handle($request->user(), $circleId);

        return ApiResponse::success(
            data: $moments
                ->map(fn ($moment): array => $presenter->make($moment, $request->user()))
                ->values()
                ->all(),
            message: 'Circle Moments retrieved.',
        );
    }
}
