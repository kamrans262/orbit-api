<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Controllers;

use App\Models\SosEvent;
use App\Modules\Sos\Actions\RespondToSosAction;
use App\Modules\Sos\Enums\SosResponderStatus;
use App\Modules\Sos\Http\Requests\RespondSosRequest;
use App\Modules\Sos\Services\SosPresenter;
use Illuminate\Http\JsonResponse;

final class RespondToSosController
{
    public function __invoke(RespondSosRequest $request, string $sosId, RespondToSosAction $action, SosPresenter $presenter): JsonResponse
    {
        $event = SosEvent::query()->findOrFail($sosId);
        $status = $request->validated('status') ?? SosResponderStatus::Engaged->value;
        $action->handle($request->user(), $event, $status);
        $event->refresh()->load('responders');

        return response()->json(['data' => $presenter->present($event, $request->user())]);
    }
}
