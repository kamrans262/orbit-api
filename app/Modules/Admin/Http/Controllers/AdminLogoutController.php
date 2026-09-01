<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Services\AdminSessionService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminLogoutController
{
    public function __invoke(Request $request, AdminSessionService $sessions, AdminAuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        /** @var AdminSession $session */
        $session = $request->attributes->get('admin_session');
        $sessions->revoke($session, 'logout');
        $audit->write('admin.auth.logout', $admin, $session, 'admin_session', $session->id, request: $request);

        return AdminApiResponse::success($request, ['signed_out' => true]);
    }
}
