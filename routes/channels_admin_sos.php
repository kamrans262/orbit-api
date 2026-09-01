<?php

declare(strict_types=1);

use App\Models\AdminUser;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin.safety', function (AdminUser $admin): bool {
    return $admin->isOperationallyActive() && $admin->hasPermission('sos.view');
});
