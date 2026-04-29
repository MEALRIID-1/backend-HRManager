<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1')),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    */
    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes - 8 heures par défaut
    |--------------------------------------------------------------------------
    */
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 480), // 8 heures

    /*
    |--------------------------------------------------------------------------
    | Refresh Token Window - Rotation automatique
    |--------------------------------------------------------------------------
    */
    'refresh_token_window' => env('SANCTUM_REFRESH_WINDOW', 120), // 2 minutes avant expiration

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    */
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'hrm_'),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Token Model
    |--------------------------------------------------------------------------
    */
    'personal_access_token_model' => 'Laravel\Sanctum\PersonalAccessToken',
];
