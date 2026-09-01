<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Controllers;

use App\Modules\Sos\Actions\ActivateSosAction;
use App\Modules\Sos\Http\Requests\ActivateSosRequest;
use App\Modules\Sos\Services\SosPresenter;
use Illuminate\Http\JsonResponse;

final class ActivateSosController
{
    public function __invoke(ActivateSosRequest $request, ActivateSosAction $action, SosPresenter $presenter): JsonResponse
    {
        $result = $action->handle($request->user(), $request->validated());

        return response()->json([
            'data' => $presenter->present($result->event, $request->user()),
            'meta' => ['idempotent_replay' => ! $result->created],
        ], $result->created ? 201 : 200);
    }
}
