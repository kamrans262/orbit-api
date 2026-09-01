<?php

declare(strict_types=1);

namespace App\Modules\Moments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Moment;
use App\Modules\Moments\Enums\MomentStatus;
use App\Modules\Moments\Exceptions\MomentException;
use App\Modules\Moments\Services\MomentAccess;
use App\Modules\Moments\Services\MomentPresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowMomentController extends Controller
{
    public function __invoke(
        Request $request,
        string $momentId,
        MomentAccess $access,
        MomentPresenter $presenter,
    ): JsonResponse {
        $moment = Moment::query()
            ->with(['author', 'mediaAsset'])
            ->withCount('views')
            ->whereKey($momentId)
            ->first();

        if ($moment === null || $moment->status === MomentStatus::Deleted || $moment->deleted_at !== null) {
            throw MomentException::notFound();
        }

        $access->viewer($request->user(), $moment->circle_id);

        if ($moment->status === MomentStatus::Expired || $moment->expires_at->isPast()) {
            throw MomentException::expired();
        }

        return ApiResponse::success(
            data: $presenter->make($moment, $request->user()),
            message: 'Moment retrieved.',
        );
    }
}
