<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Http\Controllers;

use App\Models\User;
use App\Modules\Admin\AnalyticsOperations\Services\FeatureFlagService;
use App\Modules\Admin\AnalyticsOperations\Services\RuntimeConfigService;
use Illuminate\Http\Request;

final class RuntimePlatformController
{
    public function __invoke(Request $r, FeatureFlagService $flags, RuntimeConfigService $config)
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);

        return response()->json(['success' => true, 'data' => ['feature_flags' => $flags->evaluated($u), 'remote_config' => $config->values()]]);
    }
}
