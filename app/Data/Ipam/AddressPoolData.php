<?php

namespace App\Data\Ipam;

use Spatie\LaravelData\Data;

class AddressPoolData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $total_addresses,
        public int $available_addresses,
        public int $assigned_addresses,
        /** @var array<int, NodeSummaryData> */
        public array $nodes,
        public ?string $created_at,
    ) {}

    public static function fromModel(\App\Models\AddressPool $pool): self
    {
        $totalAddresses = $pool->addresses_count ?? $pool->addresses->count();
        $availableAddresses = $pool->available_count ?? $pool->addresses->whereNull('server_id')->count();

        return new self(
            id: $pool->id,
            name: $pool->name,
            total_addresses: $totalAddresses,
            available_addresses: $availableAddresses,
            assigned_addresses: $totalAddresses - $availableAddresses,
            nodes: $pool->nodes->map(fn ($n) => [
                'id' => $n->id,
                'name' => $n->name,
            ])->toArray(),
            created_at: $pool->created_at?->toIso8601String(),
        );
    }

    public function usagePercent(): float
    {
        if ($this->total_addresses === 0) {
            return 0;
        }

        return round(($this->assigned_addresses / $this->total_addresses) * 100, 2);
    }
}
