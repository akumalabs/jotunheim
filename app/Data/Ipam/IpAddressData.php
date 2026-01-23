<?php

namespace App\Data\Ipam;

use Spatie\LaravelData\Data;

class IpAddressData extends Data
{
    public function __construct(
        public int $id,
        public string $address,
        public int $cidr,
        public string $gateway,
        public string $type,
        public ?string $mac_address,
        public bool $is_primary,
        public bool $is_assigned,
        public ?ServerSummaryData $server,
        public ?string $pool_name,
    ) {}

    public static function fromModel(\App\Models\Address $address): self
    {
        return new self(
            id: $address->id,
            address: $address->address,
            cidr: $address->cidr,
            gateway: $address->gateway,
            type: $address->type,
            mac_address: $address->mac_address,
            is_primary: $address->is_primary,
            is_assigned: $address->server_id !== null,
            server: $address->server ? ServerSummaryData::fromModel($address->server) : null,
            pool_name: $address->pool?->name,
        );
    }

    public function cidrNotation(): string
    {
        return "{$this->address}/{$this->cidr}";
    }

    public function isIpv4(): bool
    {
        return $this->type === 'ipv4';
    }

    public function isIpv6(): bool
    {
        return $this->type === 'ipv6';
    }
}

/**
 * Lightweight server summary for address assignment
 */
class ServerSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $name,
    ) {}

    public static function fromModel(\App\Models\Server $server): self
    {
        return new self(
            id: $server->id,
            uuid: $server->uuid,
            name: $server->name,
        );
    }
}
