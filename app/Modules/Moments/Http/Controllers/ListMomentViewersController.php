<?php

declare(strict_types=1);

namespace App\Modules\Moments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Moment;
use App\Modules\Moments\Enums\MomentStatus;
use App\Modules\Moments\Exceptions\MomentException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListMomentViewersController extends Controller
{
    public function __invoke(Request $request, string $momentId): JsonResponse
    {
        $moment = Moment::query()->whereKey($momentId)->first();

        if ($moment === null || $moment->status === MomentStatus::Deleted) {
            throw MomentException::notFound();
        }

        if ($moment->author_user_id !== $request->user()->id) {
            throw MomentException::forbidden();
        }

        $views = $moment->views()
            ->with('viewer')
            ->oldest('viewed_at')
            ->get();

        $visible = $views
            ->where('is_anonymous', false)
            ->map(fn ($view): array => [
                'user_id' => $view->viewer->id,
                'name' => $view->viewer->name,
                'viewed_at' => $view->viewed_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return ApiResponse::success(
            data: [
                'moment_id' => $moment->id,
                'total_views' => $views->count(),
                'anonymous_views' => $views->where('is_anonymous', true)->count(),
                'viewers' => $visible,
            ],
            message: 'Moment viewers retrieved.',
        );
    }
}
