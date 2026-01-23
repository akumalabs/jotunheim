<?php

namespace App\Enums\Activity;

enum UserActivity: string
{
    // Authentication events
    case LOGIN = 'user:auth.login';
    case LOGOUT = 'user:auth.logout';
    case LOGIN_FAILED = 'user:auth.login_failed';
    case PASSWORD_RESET = 'user:auth.password_reset';
    case TWO_FACTOR_ENABLED = 'user:auth.2fa_enabled';
    case TWO_FACTOR_DISABLED = 'user:auth.2fa_disabled';

    // Profile events
    case PROFILE_UPDATE = 'user:profile.update';
    case EMAIL_CHANGE = 'user:profile.email_change';

    // SSH Key events
    case SSH_KEY_CREATE = 'user:ssh_key.create';
    case SSH_KEY_DELETE = 'user:ssh_key.delete';

    // API Token events
    case TOKEN_CREATE = 'user:token.create';
    case TOKEN_DELETE = 'user:token.delete';

    /**
     * Get a human-readable description.
     */
    public function description(): string
    {
        return match ($this) {
            self::LOGIN => 'Logged in',
            self::LOGOUT => 'Logged out',
            self::LOGIN_FAILED => 'Failed login attempt',
            self::PASSWORD_RESET => 'Reset password',
            self::TWO_FACTOR_ENABLED => 'Enabled two-factor authentication',
            self::TWO_FACTOR_DISABLED => 'Disabled two-factor authentication',
            self::PROFILE_UPDATE => 'Updated profile',
            self::EMAIL_CHANGE => 'Changed email address',
            self::SSH_KEY_CREATE => 'Added SSH key',
            self::SSH_KEY_DELETE => 'Removed SSH key',
            self::TOKEN_CREATE => 'Created API token',
            self::TOKEN_DELETE => 'Deleted API token',
        };
    }
}
