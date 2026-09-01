<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Services\AdminRecoveryCodeService;
use App\Modules\Admin\Support\AdminApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RegenerateAdminRecoveryCodesController
{
    public function __invoke(Request $request, AdminRecoveryCodeService $codes, AdminAuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        /** @var AdminSession $session */
        $session = $request->attributes->get('admin_session');
        $newCodes = $codes->regenerate($admin);
        $audit->write('admin.mfa.recovery_codes_regenerated', $admin, $session, 'admin_user', $admin->id, request: $request);

        return AdminApiResponse::success($request, ['recovery_codes' => $newCodes, 'recovery_codes_shown_once' => true]);
    }
}
