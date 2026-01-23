<?php

namespace App\Enums\Auth;

enum TwoFactorStatus: string
{
    case PENDING = 'pending';
    case ENABLED = 'enabled';
    case VERIFIED = 'verified';
}
