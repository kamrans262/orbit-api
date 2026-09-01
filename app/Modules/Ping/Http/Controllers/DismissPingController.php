<?php

declare(strict_types=1);

namespace App\Modules\Ping\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ping\Actions\DismissPingAction;
use App\Modules\Ping\Http\Resources\PingResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DismissPingController extends Controller
{
    public function __invoke(Request $request, string $pingId, DismissPingAction $action): JsonResponse
    {
        $ping = $action->handle($request->user(), $pingId);

        return ApiResponse::success(
            data: (new PingResource($ping))->resolve($request),
            message: 'Ping dismissed.',
        );
    }
}
