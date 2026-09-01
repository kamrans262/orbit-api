<?php

declare(strict_types=1);

namespace App\Modules\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Profile\Actions\UpdateProfileAction;
use App\Modules\Profile\Http\Requests\UpdateProfileRequest;
use App\Modules\Profile\Http\Resources\ProfileResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request, UpdateProfileAction $action): JsonResponse
    {
        $user = $action->handle($request->user(), $request->validated());

        return ApiResponse::success(
            data: ProfileResource::make($user)->resolve($request),
            message: 'Profile updated successfully.',
        );
    }
}
