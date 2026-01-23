<?php

namespace App\Data\Server;

use Spatie\LaravelData\Data;

/**
 * Represents an IP address assigned to a server
 */
class AddressData extends Data
{
    public function __construct(
        public string $address,
        public int $cidr,
        public ?string $gateway,
        public string $type, // ipv4, ipv6
        public bool $isPrimary,
    ) {}

    /**
     * Create from Eloquent Address model
     */
    public static function fromModel($address): self
    {
        return new self(
            address: $address->address,
            cidr: $address->cidr,
            gateway: $address->gateway,
            type: $address->type,
            isPrimary: (bool) $address->is_primary,
        );
    }

    /**
     * Get full CIDR notation
     */
    public function cidrNotation(): string
    {
        return "{$this->address}/{$this->cidr}";
    }

    /**
     * Check if IPv4
     */
    public function isIpv4(): bool
    {
        return $this->type === 'ipv4';
    }

    /**
     * Check if IPv6
     */
    public function isIpv6(): bool
    {
        return $this->type === 'ipv6';
    }
}
