<?php

declare(strict_types=1);

return [
    'max_user_rate_limit_per_minute' => (int) env('ADMIN_MAX_USER_RATE_LIMIT_PER_MINUTE', 600),
    'allowed_user_features' => [
        'messaging', 'moments', 'ping', 'presence', 'media', 'circle_mutations',
    ],
    'allowed_circle_features' => [
        'messaging', 'moments', 'ping', 'presence', 'media', 'invites', 'membership_mutations',
    ],
    'directory_max_per_page' => 100,
];
