<?php

declare(strict_types=1);

namespace App\Modules\Presence\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Presence\Actions\UpdatePresenceAction;
use App\Modules\Presence\Http\Requests\UpdatePresenceRequest;
use App\Modules\Presence\Services\PresencePresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdatePresenceController extends Controller
{
    public function __invoke(
        UpdatePresenceRequest $request,
        UpdatePresenceAction $action,
        PresencePresenter $presenter,
    ): JsonResponse {
        $user = $request->user();
        $presence = $action->handle($user, $request->validated());

        return ApiResponse::success(
            data: $presenter->forOwner($user->refresh(), $presence),
            message: 'Presence updated.',
        );
    }
}
