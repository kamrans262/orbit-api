<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Support;

use App\Models\AdminSession;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use LogicException;

final class AdminOperationContext
{
    public static function admin(Request $request): AdminUser
    {
        $admin = $request->user();
        if (! $admin instanceof AdminUser) {
            throw new LogicException('Administrator context is unavailable.');
        }

        return $admin;
    }

    public static function session(Request $request): AdminSession
    {
        $session = $request->attributes->get('admin_session');
        if (! $session instanceof AdminSession) {
            throw new LogicException('Administrator session context is unavailable.');
        }

        return $session;
    }
}
