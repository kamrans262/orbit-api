<?php

declare(strict_types=1);

namespace App\Modules\Ping\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CircleMember;
use App\Models\Ping;
use App\Modules\Ping\Enums\PingStatus;
use App\Modules\Ping\Http\Resources\PingResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListPingInboxController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $membershipIds = CircleMember::query()
            ->where('user_id', $request->user()->id)
            ->pluck('id');

        $pings = Ping::query()
            ->with([
                'circle',
                'senderMembership.user',
                'recipientMembership.user',
            ])
            ->whereIn('recipient_membership_id', $membershipIds)
            ->where('status', PingStatus::Pending)
            ->where('expires_at', '>', now())
            ->latest()
            ->limit(max(1, (int) config('orbit.ping.list_limit', 50)))
            ->get();

        return ApiResponse::success(
            data: PingResource::collection($pings)->resolve($request),
            message: 'Ping inbox retrieved.',
        );
    }
}
