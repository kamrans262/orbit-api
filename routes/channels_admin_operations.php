<?php

declare(strict_types=1);

use App\Models\AdminUser;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin.operations', function (AdminUser $admin): bool {
    return $admin->isOperationallyActive() && $admin->hasPermission('operations.view');
});
