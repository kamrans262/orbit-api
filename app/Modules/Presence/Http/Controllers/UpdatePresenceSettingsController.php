<?php

declare(strict_types=1);

namespace App\Modules\Presence\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Presence\Actions\UpdatePresenceSettingsAction;
use App\Modules\Presence\Http\Requests\UpdatePresenceSettingsRequest;
use App\Modules\Presence\Services\PresencePresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdatePresenceSettingsController extends Controller
{
    public function __invoke(
        UpdatePresenceSettingsRequest $request,
        UpdatePresenceSettingsAction $action,
        PresencePresenter $presenter,
    ): JsonResponse {
        $user = $action->handle(
            $request->user(),
            (bool) $request->validated('global_ghost_mode'),
        );

        return ApiResponse::success(
            data: $presenter->forOwner($user, $user->presenceState()->first()),
            message: $user->global_ghost_mode ? 'Global Ghost Mode enabled.' : 'Global Ghost Mode disabled.',
        );
    }
}
