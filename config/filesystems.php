<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        // Storage privé - fichiers internes non accessibles publiquement
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Storage public - photos de profil, logos (accessible via URL)
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // S3 AWS / MinIO - documents sensibles, fiches de paie, contrats
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'eu-west-1'),
            'bucket' => env('AWS_BUCKET', 'hrmanager-storage'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'), // Pour MinIO en dev
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true), // MinIO requiert true
            'throw' => false,
            'report' => false,
        ],

        // Disk dédié aux photos de profil (sous-dossier de public)
        'photos' => [
            'driver' => 'local',
            'root' => storage_path('app/public/photos'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage/photos',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Disk dédié aux documents sensibles - accès via signed URL uniquement
        'documents' => [
            'driver' => env('DOCUMENTS_DISK_DRIVER', 's3'), // s3 ou local selon env
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'eu-west-1'),
            'bucket' => env('AWS_BUCKET', 'hrmanager-documents'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'root' => storage_path('app/private/documents'), // fallback si local
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        // Disk dédié aux fiches de paie (haute sécurité)
        'payslips' => [
            'driver' => env('PAYSLIPS_DISK_DRIVER', 's3'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'eu-west-1'),
            'bucket' => env('AWS_BUCKET_PAYSLIPS', 'hrmanager-payslips'),
            'url' => env('AWS_URL_PAYSLIPS'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'root' => storage_path('app/private/payslips'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        // Disk pour les exports temporaires (CSV, Excel)
        'exports' => [
            'driver' => 'local',
            'root' => storage_path('app/private/exports'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
