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

        /*
         * KYC documents: trade licences, NID scans, owner photographs.
         *
         * A separate disk rather than a folder on 'local', so the distinction
         * is structural. Nothing here is ever served directly — reaching a
         * file requires a signed URL issued to an authorised operator, and
         * every issue is written to the audit log.
         *
         * `throw` is deliberate: a silent false from a failed write would let
         * a verification record claim a document it does not have.
         */
        /*
         * Shop logos.
         *
         * Private, even though a logo is not secret: the icon route already
         * answers before anyone signs in, so serving through it keeps paths
         * unguessable without costing anything. A public disk would let one
         * shop's storage be enumerated from another's.
         *
         * Not in tenancy.filesystem.disks, deliberately — same as 'kyc'.
         * LogoService namespaces by tenant id instead, because the manifest
         * controller has to reach a logo and a suffixed disk would resolve
         * against whichever tenant happened to be initialised.
         */
        'logos' => [
            'driver' => 'local',
            'root' => storage_path('app/logos'),
            'visibility' => 'private',
            'throw' => true,
        ],

        'kyc' => [
            'driver' => 'local',
            'root' => storage_path('app/kyc'),
            'visibility' => 'private',
            'throw' => true,
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
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
