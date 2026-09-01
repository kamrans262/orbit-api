<?php

declare(strict_types=1);

namespace App\Modules\Activity\Http\Controllers;

use App\Modules\Activity\Actions\HideActivityAction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class HideActivityController
{
    public function __invoke(Request $request, string $activityId, HideActivityAction $hide): Response
    {
        $hide->handle($request->user(), $activityId);

        return response()->noContent();
    }
}
