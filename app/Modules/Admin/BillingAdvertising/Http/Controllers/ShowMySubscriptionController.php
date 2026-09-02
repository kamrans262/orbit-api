<?php

declare(strict_types=1);

namespace App\Modules\Admin\BillingAdvertising\Http\Controllers;

use App\Modules\Admin\BillingAdvertising\Services\BillingPresenter;
use App\Modules\Admin\BillingAdvertising\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowMySubscriptionController
{
    public function __invoke(Request $request, SubscriptionService $subscriptions, BillingPresenter $presenter): JsonResponse
    {
        $user = $request->user();

        return response()->json(['success' => true, 'data' => $presenter->subscription($subscriptions->current($user))]);
    }
}
