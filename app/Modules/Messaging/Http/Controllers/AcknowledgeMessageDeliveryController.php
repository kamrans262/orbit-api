<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Messaging\Actions\AcknowledgeMessageDeliveryAction;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AcknowledgeMessageDeliveryController extends Controller
{
    public function __invoke(
        Request $request,
        string $envelopeId,
        AcknowledgeMessageDeliveryAction $action,
    ): JsonResponse {
        return ApiResponse::success(
            $action->handle($request->user(), $envelopeId),
            'Message delivery acknowledged.',
        );
    }
}
