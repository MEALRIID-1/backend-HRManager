<?php

use Laravel\Telescope\EntryType;
use Laravel\Telescope\Telescope;

/**
 * Configuration Telescope - Monitoring Dev uniquement.
 * 
 * @performance Désactivé en production pour éviter surcharge base de données
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Telescope Domain
    |--------------------------------------------------------------------------
    */
    'domain' => env('TELESCOPE_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Telescope Path
    |--------------------------------------------------------------------------
    */
    'path' => env('TELESCOPE_PATH', 'telescope'),

    /*
    |--------------------------------------------------------------------------
    | Storage Driver
    |--------------------------------------------------------------------------
    */
    'driver' => env('TELESCOPE_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue for pruning and storing Telescope data.
    */
    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION', null),
        'queue' => env('TELESCOPE_QUEUE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('TELESCOPE_CACHE', true),
        'driver' => env('TELESCOPE_CACHE_DRIVER', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Master Switch - DÉSACTIVÉ EN PRODUCTION
    |--------------------------------------------------------------------------
    |
    | ⚠️ CRITIQUE: Toujours mettre TELESCOPE_ENABLED=false en production!
    |
    */
    'enabled' => env('TELESCOPE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Authorization - Accessible uniquement aux admins
    |--------------------------------------------------------------------------
    */
    'middleware' => ['web', 'auth'],

    'authorized' => env('TELESCOPE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Filter Configuration - Limite les entrées en dev
    |--------------------------------------------------------------------------
    */
    'filter' => [
        // En production, ne rien logguer (désactivé pour config:cache)
        'production' => false,

        // En dev, tout logger (désactivé pour config:cache)
        'local' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Watcher Configuration - Watchers à activer
    |--------------------------------------------------------------------------
    */
    'watchers' => [
        // Core Watchers (toujours utiles)
        'request' => [
            'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64), // KB
            'ignore_statuses' => [404, 200], // Ignorer les 200/404 bruyants
        ],
        'command' => env('TELESCOPE_COMMAND_WATCHER', true),
        'schedule' => env('TELESCOPE_SCHEDULE_WATCHER', true),
        'job' => env('TELESCOPE_JOB_WATCHER', true),
        'exception' => env('TELESCOPE_EXCEPTION_WATCHER', true),
        'log' => env('TELESCOPE_LOG_WATCHER', true),
        'model' => env('TELESCOPE_MODEL_WATCHER', true),
        'event' => env('TELESCOPE_EVENT_WATCHER', false), // Trop bruyant
        'gate' => env('TELESCOPE_GATE_WATCHER', false),
        'cache' => env('TELESCOPE_CACHE_WATCHER', false),
        'query' => [
            'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
            'ignore_packages' => true,
            'slow' => 100, // Log queries > 100ms
        ],
        'redis' => env('TELESCOPE_REDIS_WATCHER', false),
        'view' => env('TELESCOPE_VIEW_WATCHER', false),
        'mail' => env('TELESCOPE_MAIL_WATCHER', true),
        'notification' => env('TELESCOPE_NOTIFICATION_WATCHER', false),
        'dump' => env('TELESCOPE_DUMP_WATCHER', true),

        // Performance Watchers
        'client_request' => env('TELESCOPE_CLIENT_REQUEST_WATCHER', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning Configuration - Nettoyage automatique
    |--------------------------------------------------------------------------
    |
    | Garde seulement 24h de données en dev/staging
    */
    'pruning' => [
        'enabled' => true,
        'limit' => 10000, // Maximum 10k entrées
        'hours' => 24,    // Suppression après 24h
    ],

    /*
    |--------------------------------------------------------------------------
    | Records Per Minute Limit - Rate limiting des logs
    |--------------------------------------------------------------------------
    */
    'limit' => env('TELESCOPE_LIMIT', 60), // 60 records/min max

    /*
    |--------------------------------------------------------------------------
    | Nightingale (Grafana Cloud) Configuration
    |--------------------------------------------------------------------------
    */
    'nightingale' => [
        'enabled' => env('TELESCOPE_NIGHTINGALE_ENABLED', false),
    ],
];
