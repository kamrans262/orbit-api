<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Messaging\Actions\SyncEncryptedMessagesAction;
use App\Modules\Messaging\Http\Requests\SyncEncryptedMessagesRequest;
use App\Modules\Messaging\Http\Resources\EncryptedEnvelopeResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListCircleMessagesController extends Controller
{
    public function __invoke(
        SyncEncryptedMessagesRequest $request,
        string $circleId,
        SyncEncryptedMessagesAction $action,
    ): JsonResponse {
        $result = $action->handle(
            user: $request->user(),
            deviceId: $request->string('device_id')->toString(),
            afterId: $request->integer('after_id', 0),
            limit: $request->integer('limit', 200),
            circleId: $circleId,
        );

        return ApiResponse::success([
            'envelopes' => EncryptedEnvelopeResource::collection($result['envelopes'])->resolve($request),
            'next_cursor' => $result['next_cursor'],
            'has_more' => $result['has_more'],
        ], 'Encrypted Circle messages synchronized.');
    }
}
