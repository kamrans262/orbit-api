<?php

declare(strict_types=1);

namespace App\Modules\Moments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Moments\Actions\PublishMomentAction;
use App\Modules\Moments\Http\Requests\PublishMomentRequest;
use App\Modules\Moments\Services\MomentPresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PublishMomentController extends Controller
{
    public function __invoke(
        PublishMomentRequest $request,
        string $circleId,
        PublishMomentAction $action,
        MomentPresenter $presenter,
    ): JsonResponse {
        $moment = $action->handle(
            $request->user(),
            $circleId,
            $request->string('moment_id')->toString(),
            $request->string('media_asset_id')->toString(),
            $request->integer('ttl_seconds') ?: null,
        );

        return ApiResponse::success(
            data: $presenter->make($moment, $request->user()),
            message: 'Moment published.',
            status: 201,
        );
    }
}
