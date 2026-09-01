<?php

declare(strict_types=1);

return [
    'auth' => [
        'email_otp' => [
            'ttl_minutes' => 10,
            'max_attempts' => 5,
        ],
        'token_expiration_days' => 30,
    ],

    'presence' => [
        'offline_after_seconds' => 120,
        'approximate_precision_decimals' => 2,
    ],

    'ping' => [
        'ttl_seconds' => 120,
        'cooldown_seconds' => 10,
        'list_limit' => 50,
    ],

    'messaging' => [
        'server_retention_days' => 30,
        'sync_limit_max' => 200,
        'typing_throttle_seconds' => 3,
        'typing_expiry_seconds' => 5,
    ],
];
