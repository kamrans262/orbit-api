<?php

declare(strict_types=1);

namespace App\Modules\Ping\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ping\Actions\RespondToPingAction;
use App\Modules\Ping\Enums\PingResponseType;
use App\Modules\Ping\Http\Requests\RespondToPingRequest;
use App\Modules\Ping\Http\Resources\PingResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RespondToPingController extends Controller
{
    public function __invoke(
        RespondToPingRequest $request,
        string $pingId,
        RespondToPingAction $action,
    ): JsonResponse {
        $ping = $action->handle(
            $request->user(),
            $pingId,
            PingResponseType::from($request->string('response_type')->toString()),
        );

        return ApiResponse::success(
            data: (new PingResource($ping))->resolve($request),
            message: 'Ping response sent.',
        );
    }
}
