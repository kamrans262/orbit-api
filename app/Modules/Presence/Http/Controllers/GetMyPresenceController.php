<?php

declare(strict_types=1);

namespace App\Modules\Presence\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Presence\Services\PresencePresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetMyPresenceController extends Controller
{
    public function __invoke(Request $request, PresencePresenter $presenter): JsonResponse
    {
        $user = $request->user();
        $presence = $user->presenceState()->first();

        return ApiResponse::success(
            data: $presenter->forOwner($user, $presence),
        );
    }
}
