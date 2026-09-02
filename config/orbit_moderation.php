<?php

declare(strict_types=1);

return [
    'consumer_report_rate_limit' => 20,
    'max_evidence_refs' => 5,
    'allowed_target_types' => ['user', 'circle', 'message', 'moment', 'ping', 'sos', 'activity'],
    'allowed_reasons' => [
        'harassment', 'spam', 'threats', 'abuse', 'fake_account', 'safety', 'sos_misuse', 'other',
    ],
    'allowed_enforcements' => [
        'warn_user',
        'restrict_user_feature',
        'suspend_user_temp',
        'suspend_user_indefinite',
        'restore_user',
        'freeze_circle',
        'restore_circle',
        'remove_circle',
    ],
];
