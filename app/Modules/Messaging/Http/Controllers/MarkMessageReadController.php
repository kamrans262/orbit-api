<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Messaging\Actions\MarkMessageReadAction;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MarkMessageReadController extends Controller
{
    public function __invoke(
        Request $request,
        string $circleId,
        string $messageId,
        MarkMessageReadAction $action,
    ): JsonResponse {
        return ApiResponse::success(
            $action->handle($request->user(), $circleId, $messageId),
            'Message read state processed.',
        );
    }
}
