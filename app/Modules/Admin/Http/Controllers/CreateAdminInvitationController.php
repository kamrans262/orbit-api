<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminUser;
use App\Modules\Admin\Exceptions\AdminOperationException;
use App\Modules\Admin\Http\Requests\CreateAdminInvitationRequest;
use App\Modules\Admin\Services\AdminInvitationService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

final class CreateAdminInvitationController
{
    public function __invoke(CreateAdminInvitationRequest $request, AdminInvitationService $invitations): JsonResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->user();
        try {
            $result = $invitations->invite(
                (string) $request->input('email'),
                $request->filled('name') ? (string) $request->input('name') : null,
                array_values($request->input('role_slugs', [])),
                $actor,
                $request,
                (string) $request->input('reason'),
                $request->filled('access_expires_at') ? Carbon::parse((string) $request->input('access_expires_at')) : null,
            );
        } catch (AdminOperationException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, [
            'id' => $result['invitation']->id,
            'admin_id' => $result['admin']->id,
            'email' => $result['admin']->email,
            'status' => $result['admin']->status->value,
            'expires_at' => $result['invitation']->expires_at->toIso8601String(),
            'delivery' => 'email',
        ], 201, 'Administrator invitation created.');
    }
}
