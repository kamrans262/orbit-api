<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Messaging\Actions\SendTypingIndicatorAction;
use App\Modules\Messaging\Http\Requests\TypingIndicatorRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TypingIndicatorController extends Controller
{
    public function __invoke(
        TypingIndicatorRequest $request,
        string $circleId,
        SendTypingIndicatorAction $action,
    ): JsonResponse {
        return ApiResponse::success(
            $action->handle($request->user(), $circleId, $request->boolean('is_typing')),
            'Typing state processed.',
        );
    }
}
