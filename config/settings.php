<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    |
    | Configure backup-related settings
    |
    */

    'backup' => [
        // Maximum number of backups allowed per server
        'max_backups' => env('BACKUP_MAX_BACKUPS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Settings
    |--------------------------------------------------------------------------
    |
    | Configure server-related settings
    |
    */

    'server' => [
        // Default password generation length
        'default_password_length' => env('SERVER_DEFAULT_PASSWORD_LENGTH', 16),

        // Maximum password length
        'max_password_length' => env('SERVER_MAX_PASSWORD_LENGTH', 72),

        // Minimum password length
        'min_password_length' => env('SERVER_MIN_PASSWORD_LENGTH', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for API endpoints
    |
    */

    'rate_limit' => [
        // Maximum rebuild attempts per hour per server
        'rebuild_per_hour' => env('RATE_LIMIT_REBUILD_PER_HOUR', 3),

        // Maximum power control attempts per minute per server
        'power_per_minute' => env('RATE_LIMIT_POWER_PER_MINUTE', 6),

        // Maximum backup creation attempts per hour per server
        'backup_per_hour' => env('RATE_LIMIT_BACKUP_PER_HOUR', 2),
    ],

];
