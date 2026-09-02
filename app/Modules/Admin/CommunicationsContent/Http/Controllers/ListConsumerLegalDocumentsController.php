<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\UserRegionalProfile;
use App\Modules\Admin\CommunicationsContent\Services\ConsumerContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListConsumerLegalDocumentsController
{
    public function __invoke(Request $request, ConsumerContentService $service): JsonResponse
    {
        $profile = UserRegionalProfile::query()->where('user_id', $request->user()->getKey())->first();
        $locale = strtolower((string) $request->query('locale', $profile?->locale ?? $request->user()->locale ?? 'en'));
        $country = $request->query('country', $profile?->country_code);

        return response()->json([
            'data' => $service->legal($request->user(), $locale, $country ? (string) $country : null),
        ]);
    }
}
