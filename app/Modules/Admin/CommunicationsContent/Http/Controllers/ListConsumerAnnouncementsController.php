<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\UserRegionalProfile;
use App\Modules\Admin\CommunicationsContent\Services\ConsumerContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListConsumerAnnouncementsController
{
    public function __invoke(Request $request, ConsumerContentService $service): JsonResponse
    {
        $profile = UserRegionalProfile::query()->where('user_id', $request->user()->getKey())->first();
        $locale = strtolower((string) $request->query('locale', $profile?->locale ?? $request->user()->locale ?? 'en'));

        return response()->json(['data' => $service->announcements($request->user(), $locale)]);
    }
}
