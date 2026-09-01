<?php

declare(strict_types=1);

return [
    'disk' => env('ORBIT_MEDIA_DISK', 'local'),

    'upload_ttl_minutes' => (int) env('ORBIT_MEDIA_UPLOAD_TTL_MINUTES', 60),

    'max_size_bytes' => (int) env('ORBIT_MEDIA_MAX_SIZE_BYTES', 104857600),

    'chunk_size_bytes' => (int) env('ORBIT_MEDIA_CHUNK_SIZE_BYTES', 5242880),

    'max_key_envelope_bytes' => (int) env('ORBIT_MEDIA_MAX_KEY_ENVELOPE_BYTES', 8192),

    'asset_retention_days' => (int) env('ORBIT_MEDIA_ASSET_RETENTION_DAYS', 30),
];
