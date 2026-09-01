<?php

declare(strict_types=1);

namespace App\Modules\Sos\Http\Controllers;

use App\Models\SosEvent;
use App\Modules\Sos\Services\SosAccessService;
use App\Modules\Sos\Services\SosPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowSosController
{
    public function __invoke(Request $request, string $sosId, SosAccessService $access, SosPresenter $presenter): JsonResponse
    {
        $event = SosEvent::query()->with('responders')->findOrFail($sosId);
        $access->assertEventMember($request->user(), $event);

        return response()->json(['data' => $presenter->present($event, $request->user())]);
    }
}
