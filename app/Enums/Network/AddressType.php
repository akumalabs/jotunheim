<?php

namespace App\Enums\Network;

enum AddressType: string
{
    case IPV4 = 'ipv4';
    case IPV6 = 'ipv6';

    /**
     * Detect type from address string.
     */
    public static function fromAddress(string $address): self
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? self::IPV6
            : self::IPV4;
    }

    /**
     * Get default CIDR for this type.
     */
    public function defaultCidr(): int
    {
        return match ($this) {
            self::IPV4 => 24,
            self::IPV6 => 64,
        };
    }

    /**
     * Get maximum CIDR for this type.
     */
    public function maxCidr(): int
    {
        return match ($this) {
            self::IPV4 => 32,
            self::IPV6 => 128,
        };
    }
}
