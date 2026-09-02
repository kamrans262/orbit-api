<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\BillingAdvertising\Services\SubscriptionService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowUserSubscriptionController
{
    public function __invoke(Request $request, int $userId, SubscriptionService $subscriptions, BillingPresenter $presenter): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return AdminApiResponse::error($request, 'User not found.', 'USER_NOT_FOUND', 404);
        }

return AdminApiResponse::success($request, $presenter->subscription($subscriptions->current($user)));
    }
}
