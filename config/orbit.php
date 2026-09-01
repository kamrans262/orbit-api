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
        // Presence older than this is presented as offline. The latest permitted
        // location can still be returned with its timestamp unless privacy hides it.
        'offline_after_seconds' => 120,

        // Two decimal places is roughly neighborhood-level precision.
        'approximate_precision_decimals' => 2,
    ],
];
