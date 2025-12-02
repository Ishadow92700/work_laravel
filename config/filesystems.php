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

    // 'sftp' is set as the default disk to enable remote file storage and access via SFTP,
    // which is preferred over 'local' for enhanced security and centralized file management.
    'default' => env('FILESYSTEM_DISK', 'sftp'),

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

        'sftp' => [
        'driver' => 'sftp',
        'host' => env('SFTP_HOST', '149.202.40.76'),
        'username' => env('SFTP_USERNAME', 'Divalto_test'),
        'password' => env('SFTP_PASSWORD', 'E9f25F6WiyqgJ7'),
        'root' => env('SFTP_ROOT', '/uploads/erp_to_periscope'),
        'port' => (int) env('SFTP_PORT', 22), // <- conversion en int !
        'passive' => env('SFTP_PASSIVE', true),
        'timeout' => 30,
        'throw' => false,
        'report' => false,
    ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID', null),
            'secret' => env('AWS_SECRET_ACCESS_KEY', null),
            'region' => env('AWS_DEFAULT_REGION', null),
            'bucket' => env('AWS_BUCKET', null),
            'url' => env('AWS_URL', ''),
            'endpoint' => env('AWS_ENDPOINT', ''),
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
