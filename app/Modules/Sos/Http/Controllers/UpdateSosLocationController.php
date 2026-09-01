<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Controllers;

use App\Models\SosEvent;
use App\Modules\Sos\Actions\UpdateSosLocationAction;
use App\Modules\Sos\Http\Requests\UpdateSosLocationRequest;
use Illuminate\Http\JsonResponse;

final class UpdateSosLocationController
{
    public function __invoke(UpdateSosLocationRequest $request, string $sosId, UpdateSosLocationAction $action): JsonResponse
    {
        $event = SosEvent::query()->findOrFail($sosId);
        $action->handle($request->user(), $event, $request->validated());

        return response()->json(['data' => ['accepted' => true]]);
    }
}
