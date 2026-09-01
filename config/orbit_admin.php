<?php

declare(strict_types=1);

return [
    'console_url' => env('ADMIN_CONSOLE_URL', 'http://localhost:3000/admin'),
    'session_lifetime_minutes' => (int) env('ADMIN_SESSION_LIFETIME_MINUTES', 480),
    'idle_timeout_minutes' => (int) env('ADMIN_IDLE_TIMEOUT_MINUTES', 15),
    'reauth_window_minutes' => (int) env('ADMIN_REAUTH_WINDOW_MINUTES', 10),
    'mfa_challenge_minutes' => (int) env('ADMIN_MFA_CHALLENGE_MINUTES', 5),
    'mfa_setup_minutes' => (int) env('ADMIN_MFA_SETUP_MINUTES', 20),
    'mfa_max_attempts' => (int) env('ADMIN_MFA_MAX_ATTEMPTS', 5),
    'invitation_hours' => (int) env('ADMIN_INVITATION_HOURS', 24),
    'failed_login_limit' => (int) env('ADMIN_FAILED_LOGIN_LIMIT', 5),
    'lockout_minutes' => (int) env('ADMIN_LOCKOUT_MINUTES', 15),
    'recovery_code_count' => (int) env('ADMIN_RECOVERY_CODE_COUNT', 10),
];
