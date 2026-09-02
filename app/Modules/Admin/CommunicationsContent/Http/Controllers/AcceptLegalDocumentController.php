<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Http\Controllers;

use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\UserRegionalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AcceptLegalDocumentController
{
    public function __invoke(Request $request, string $legalId): JsonResponse
    {
        $document = LegalDocument::query()
            ->whereKey($legalId)
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('effective_at')->orWhere('effective_at', '<=', now()))
            ->first();

        if (! $document || ! $this->appliesToUser($document, (int) $request->user()->getKey())) {
            return response()->json(['message' => 'Legal document not found.', 'code' => 'LEGAL_DOCUMENT_NOT_FOUND'], 404);
        }

        $acceptance = LegalAcceptance::query()->updateOrCreate(
            ['legal_document_id' => $document->id, 'user_id' => $request->user()->getKey()],
            ['accepted_at' => now(), 'source' => 'consumer'],
        );

        return response()->json(['data' => [
            'legal_document_id' => $document->id,
            'version' => $document->version,
            'accepted_at' => $acceptance->accepted_at?->toIso8601String(),
        ]]);
    }

    private function appliesToUser(LegalDocument $document, int $userId): bool
    {
        $regions = $document->regions ?? [];
        if ($regions === []) {
            return true;
        }

        $country = UserRegionalProfile::query()->where('user_id', $userId)->value('country_code');
        if (! $country) {
            return false;
        }

        return in_array(
            strtoupper((string) $country),
            array_map(fn ($value): string => strtoupper((string) $value), $regions),
            true,
        );
    }
}
