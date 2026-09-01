<?php

declare(strict_types=1);

namespace App\Modules\Ping\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ping\Actions\SendPingAction;
use App\Modules\Ping\Http\Requests\SendPingRequest;
use App\Modules\Ping\Http\Resources\PingResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SendPingController extends Controller
{
    public function __invoke(SendPingRequest $request, SendPingAction $action): JsonResponse
    {
        $ping = $action->handle(
            $request->user(),
            $request->string('circle_id')->toString(),
            $request->string('recipient_membership_id')->toString(),
        );

        return ApiResponse::success(
            data: (new PingResource($ping))->resolve($request),
            message: 'Ping sent.',
            status: 201,
        );
    }
}
