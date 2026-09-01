<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Controllers;

use App\Models\SosEvent;
use App\Modules\Sos\Actions\ResolveSosAction;
use App\Modules\Sos\Http\Requests\ResolveSosRequest;
use App\Modules\Sos\Services\SosPresenter;
use Illuminate\Http\JsonResponse;

final class ResolveSosController
{
    public function __invoke(ResolveSosRequest $request, string $sosId, ResolveSosAction $action, SosPresenter $presenter): JsonResponse
    {
        $event = SosEvent::query()->findOrFail($sosId);
        $resolved = $action->handle($request->user(), $event, $request->validated('reason'));

        return response()->json(['data' => $presenter->present($resolved->load('responders'), $request->user())]);
    }
}
