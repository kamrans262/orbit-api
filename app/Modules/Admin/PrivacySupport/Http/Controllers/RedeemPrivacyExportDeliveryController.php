<?php

declare(strict_types=1);

namespace App\Modules\Admin\PrivacySupport\Http\Controllers;

use App\Models\DataExportRequest;
use App\Models\PrivacyExportDeliveryLink;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Modules\Admin\PrivacySupport\Services\ContactHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RedeemPrivacyExportDeliveryController
{
    public function __invoke(Request $request, string $token, ContactHistoryService $contacts): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $link = PrivacyExportDeliveryLink::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($link === null) {
            return response()->json(['success' => false, 'code' => 'EXPORT_DELIVERY_UNAVAILABLE'], 404);
        }

        $export = DataExportRequest::query()
            ->whereKey($link->data_export_request_id)
            ->where('user_id', $user->id)
            ->where('status', 'ready')
            ->whereNotNull('payload')
            ->where('expires_at', '>', now())
            ->first();

        if ($export === null) {
            return response()->json(['success' => false, 'code' => 'EXPORT_DELIVERY_UNAVAILABLE'], 404);
        }

        if ($link->delivered_at === null) {
            $link->forceFill(['delivered_at' => now()])->save();
            $contacts->record(
                (int) $user->id, 'privacy.export.delivered', 'api', 'outbound',
                'Data export delivered', 'Your Orbit data export was accessed.',
                'data_export', $export->id,
            );

            PrivacyRequest::query()
                ->where('linked_data_export_id', $export->id)
                ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
                ->update([
                    'status' => 'completed',
                    'resolution' => 'Data export delivered to the authenticated account.',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'export_id' => $export->id,
                'generated_at' => $export->completed_at?->toIso8601String(),
                'expires_at' => $export->expires_at?->toIso8601String(),
                'payload' => $export->payload,
            ],
        ]);
    }
}
