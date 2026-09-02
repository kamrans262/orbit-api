<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Models\User;
use App\Modules\Dashboard\Services\MemberRecentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowMemberRecentController
{
    public function __invoke(Request $request, string $userId, MemberRecentService $service): JsonResponse
    {
        $target = ctype_digit($userId) ? User::query()->find((int) $userId) : null;
        abort_unless($target, 404);

        return response()->json(['data' => $service->forUser($request->user(), $target)]);
    }
}
