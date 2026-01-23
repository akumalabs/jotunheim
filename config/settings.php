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

];
