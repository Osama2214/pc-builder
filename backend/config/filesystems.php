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

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Local disk by default (files live in storage/app/public, served via the
        // storage:link symlink). Set PUBLIC_DISK_DRIVER=s3 in production to point
        // this same disk at an S3-compatible bucket (e.g. Cloudflare R2) instead —
        // every Storage::disk('public') call in the app keeps working unchanged.
        'public' => [
            'driver' => env('PUBLIC_DISK_DRIVER', 'local'),
            // Only meaningful for the local driver — Flysystem's S3 adapter treats "root"
            // as a literal key prefix, so leaving this set to an absolute container path
            // (e.g. /var/www/html/storage/app/public) while PUBLIC_DISK_DRIVER=s3 uploads
            // every file under that whole path inside the bucket and bakes it into every
            // generated URL too. Must be null/empty on s3 so objects sit at a clean key
            // (e.g. products/xxx.png) instead of var/www/html/storage/app/public/products/xxx.png.
            'root' => env('PUBLIC_DISK_DRIVER', 'local') === 'local' ? storage_path('app/public') : '',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,

            // Local-driver key, ignored by the s3 driver.
            'url' => env('AWS_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/storage'),

            // S3/R2-driver keys, ignored by the local driver.
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
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
