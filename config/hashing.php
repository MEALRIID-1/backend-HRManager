<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Ce driver est utilisé pour hacher les mots de passe.
    | Options: "bcrypt", "argon", "argon2id"
    |
    */
    'driver' => env('HASH_DRIVER', 'bcrypt'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | rounds: nombre d'itérations (12 minimum recommandé pour production)
    |
    */
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12), // Cost 12 pour production (sécurité renforcée)
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | memory: mémoire en kilo-octets
    | threads: nombre de threads parallèles
    | time: temps de calcul
    |
    */
    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | Réhacher automatiquement les mots de passe si les paramètres changent.
    |
    */
    'rehash_on_login' => true,
];
