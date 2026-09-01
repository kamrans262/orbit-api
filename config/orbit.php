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
];
