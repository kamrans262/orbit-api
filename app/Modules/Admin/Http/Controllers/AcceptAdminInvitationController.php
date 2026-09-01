<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Exceptions\AdminOperationException;
use App\Modules\Admin\Http\Requests\AcceptAdminInvitationRequest;
use App\Modules\Admin\Services\AdminInvitationService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;

final class AcceptAdminInvitationController
{
    public function __invoke(AcceptAdminInvitationRequest $request, AdminInvitationService $invitations): JsonResponse
    {
        try {
            $result = $invitations->accept(
                (string) $request->input('invitation_token'),
                (string) $request->input('name'),
                (string) $request->input('password'),
                $request,
            );
        } catch (AdminOperationException $exception) {
            return AdminApiResponse::error($request, $exception->getMessage(), $exception->errorCode, $exception->status);
        }

        return AdminApiResponse::success($request, [
            'admin_id' => $result['admin']->id,
            'email' => $result['admin']->email,
            'setup_token' => $result['setup_token'],
            'totp_provisioning_uri' => $result['provisioning_uri'],
            'expires_at' => $result['expires_at'],
        ]);
    }
}
