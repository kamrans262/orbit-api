<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use App\Modules\Admin\Moderation\Services\ModerationIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateConsumerReportController
{
    public function __invoke(Request $request, ModerationIntakeService $intake): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['success' => false, 'code' => 'UNAUTHENTICATED'], 401);
        }
        $data = $request->validate([
            'client_report_id' => ['nullable', 'uuid'],
            'target_type' => ['required', 'string', Rule::in((array) config('orbit_moderation.allowed_target_types', []))],
            'target_id' => ['required', 'string', 'max:64'],
            'reason' => ['required', 'string', Rule::in((array) config('orbit_moderation.allowed_reasons', []))],
            'details' => ['nullable', 'string', 'max:1000'],
            'evidence_text' => ['nullable', 'string', 'max:2000'],
            'evidence_refs' => ['nullable', 'array', 'max:'.(int) config('orbit_moderation.max_evidence_refs', 5)],
            'evidence_refs.*' => ['string', 'max:255'],
        ]);
        try {
            $report = $intake->create($user, $data);
        } catch (ModerationDomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => $e->errorCode], $e->status);
        }

        return response()->json(['success' => true, 'data' => ['id' => (string) $report->id, 'status' => $report->status, 'priority' => $report->priority]], 202);
    }
}
