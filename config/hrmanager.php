<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | HRManager Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration specific to the HRManager application.
    |
    */

    // Password settings
    'password_change_days' => env('HRMANAGER_DEFAULT_PASSWORD_CHANGE_DAYS', 90),
    'max_login_attempts' => env('HRMANAGER_MAX_LOGIN_ATTEMPTS', 5),
    'login_lockout_minutes' => env('HRMANAGER_LOGIN_LOCKOUT_MINUTES', 30),
    'password_reset_token_expiry' => env('HRMANAGER_PASSWORD_RESET_TOKEN_EXPIRY', 60),

    // Leave settings
    'default_annual_leave_days' => 25,
    'max_consecutive_leave_days' => 30,
    'min_notice_days' => 7,

    // Contract settings
    'contract_expiry_warning_days' => [30, 60, 90],

    // Notification settings
    'notifications_per_page' => 15,

    // File upload settings
    'max_document_size_kb' => 10240,
    'allowed_document_types' => ['pdf', 'doc', 'docx'],

    // API settings
    'api_pagination_default' => 15,
    'api_pagination_max' => 100,
];
