<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\IssueIdentitySessionAction;
use App\Modules\Identity\Http\Requests\IssueIdentitySessionRequest;
use Illuminate\Http\JsonResponse;

final class IssueIdentitySessionController
{
    public function __invoke(IssueIdentitySessionRequest $request, IssueIdentitySessionAction $action): JsonResponse
    {
        return response()->json([
            'data' => $action->handle(
                $request->user(),
                (string) $request->validated('device_id'),
                $request,
            ),
        ], 201);
    }
}
