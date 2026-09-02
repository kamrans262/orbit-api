<?php

declare(strict_types=1);

namespace App\Modules\Admin\AnalyticsOperations\Services;

use App\Models\RemoteConfigEntry;

final class RuntimeConfigService
{
    public function values(string $environment = 'production'): array
    {
        $out = [];
        foreach (RemoteConfigEntry::query()->where('environment', $environment)->where('status', 'active')->get() as $e) {
            data_set($out, $e->key, $e->value['value'] ?? $e->value);
        }

return $out;
    }
}
