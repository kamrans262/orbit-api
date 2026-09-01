<?php

declare(strict_types=1);

return [
    'default_ttl_seconds' => (int) env('ORBIT_MOMENT_TTL_SECONDS', 86400),
    'max_ttl_seconds' => (int) env('ORBIT_MOMENT_MAX_TTL_SECONDS', 86400),
    'feed_limit' => (int) env('ORBIT_MOMENT_FEED_LIMIT', 100),
    'view_receipt_retention_days' => (int) env('ORBIT_MOMENT_VIEW_RETENTION_DAYS', 7),
];
