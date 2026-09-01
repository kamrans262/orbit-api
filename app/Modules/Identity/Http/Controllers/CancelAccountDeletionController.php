<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\CancelAccountDeletionAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CancelAccountDeletionController
{
    public function __invoke(Request $request, CancelAccountDeletionAction $action): JsonResponse
    {
        $deletion = $action->handle($request->user(), $request);

        return response()->json(['data' => [
            'cancelled' => $deletion !== null,
            'id' => $deletion?->id,
            'status' => $deletion?->status,
        ]]);
    }
}
