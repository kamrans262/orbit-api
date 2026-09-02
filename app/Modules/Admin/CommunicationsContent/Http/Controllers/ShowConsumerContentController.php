<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\UserRegionalProfile;
use App\Modules\Admin\CommunicationsContent\Services\ConsumerContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowConsumerContentController
{
    public function __invoke(Request $request, string $slug, ConsumerContentService $service): JsonResponse
    {
        $profile = UserRegionalProfile::query()->where('user_id', $request->user()->getKey())->first();
        $locale = strtolower((string) $request->query('locale', $profile?->locale ?? $request->user()->locale ?? 'en'));
        $country = $request->query('country', $profile?->country_code);
        $item = $service->content($slug, $locale, $country ? (string) $country : null);

        return $item
            ? response()->json(['data' => $item])
            : response()->json(['message' => 'Content not found.', 'code' => 'CONTENT_NOT_FOUND'], 404);
    }
}
