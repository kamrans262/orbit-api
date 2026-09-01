<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Modules\Messaging\Exceptions\MessagingException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetCircleMessageDevicesController extends Controller
{
    public function __invoke(Request $request, string $circleId): JsonResponse
    {
        $circle = Circle::query()->available()->find($circleId);

        if ($circle === null) {
            throw MessagingException::circleNotFound();
        }

        $requester = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($requester === null) {
            throw MessagingException::circleNotFound();
        }

        if (! $requester->can_message) {
            throw MessagingException::messagingDisabled();
        }

        $memberships = CircleMember::query()
            ->with(['user.devices' => function ($query): void {
                $query->whereNull('revoked_at')
                    ->whereNotNull('public_identity_key')
                    ->orderBy('id');
            }])
            ->where('circle_id', $circle->id)
            ->where('can_message', true)
            ->orderBy('joined_at')
            ->get();

        $data = $memberships->map(fn (CircleMember $membership): array => [
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'name' => $membership->user->name,
            'devices' => $membership->user->devices->map(fn ($device): array => [
                'device_id' => $device->id,
                'platform' => $device->platform,
                'public_identity_key' => $device->public_identity_key,
                'key_updated_at' => $device->updated_at?->toIso8601String(),
            ])->values()->all(),
        ])->values()->all();

        return ApiResponse::success($data, 'Circle message device keys retrieved.');
    }
}
