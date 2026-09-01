<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Controllers;

use App\Models\SosEvent;
use App\Modules\Sos\Actions\AttachSosRecordingAction;
use App\Modules\Sos\Http\Requests\AttachSosRecordingRequest;
use App\Modules\Sos\Services\SosPresenter;
use Illuminate\Http\JsonResponse;

final class AttachSosRecordingController
{
    public function __invoke(AttachSosRecordingRequest $request, string $sosId, AttachSosRecordingAction $action, SosPresenter $presenter): JsonResponse
    {
        $event = SosEvent::query()->findOrFail($sosId);
        $updated = $action->handle($request->user(), $event, $request->validated('recording_ref'));

        return response()->json(['data' => $presenter->present($updated->load('responders'), $request->user())]);
    }
}
