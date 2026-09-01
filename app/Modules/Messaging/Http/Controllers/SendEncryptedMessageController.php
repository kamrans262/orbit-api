<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Messaging\Actions\SendEncryptedMessageAction;
use App\Modules\Messaging\Http\Requests\SendEncryptedMessageRequest;
use App\Modules\Messaging\Http\Resources\MessageResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SendEncryptedMessageController extends Controller
{
    public function __invoke(
        SendEncryptedMessageRequest $request,
        string $circleId,
        SendEncryptedMessageAction $action,
    ): JsonResponse {
        $result = $action->handle($request->user(), $circleId, $request->validated());

        return ApiResponse::success(
            data: [
                'message' => (new MessageResource($result['message']))->resolve($request),
                'duplicate' => $result['duplicate'],
            ],
            message: $result['duplicate'] ? 'Message already accepted.' : 'Encrypted message accepted.',
            status: $result['duplicate'] ? 200 : 201,
        );
    }
}
