<?php

namespace App\Services\Ipam;

use App\Data\Ipam\IpAddressData;
use App\Models\Address;
use App\Models\Node;
use App\Models\Server;
use Illuminate\Support\Collection;

class AddressService
{
    /**
     * Allocate an address to a server.
     */
    public function allocate(Server $server, ?string $preferredAddress = null): Address
    {
        $node = $server->node;

        // Get available pools for this node
        $pools = $node->addressPools()->with(['addresses' => function ($q) {
            $q->whereNull('server_id');
        }])->get();

        $address = null;

        if ($preferredAddress) {
            // Try to get the preferred address
            $address = Address::where('address', $preferredAddress)
                ->whereNull('server_id')
                ->whereHas('pool.nodes', fn ($q) => $q->where('nodes.id', $node->id))
                ->first();
        }

        if (! $address) {
            // Get first available address
            $address = $pools->flatMap(fn ($pool) => $pool->addresses)->first();
        }

        if (! $address) {
            throw new \Exception('No available addresses for this node');
        }

        // Assign to server
        $address->update([
            'server_id' => $server->id,
            'is_primary' => $server->addresses()->count() === 0,
        ]);

        return $address->fresh();
    }

    /**
     * Release an address from a server.
     */
    public function release(Address $address): void
    {
        $wasPrimary = $address->is_primary;
        $serverId = $address->server_id;

        $address->update([
            'server_id' => null,
            'is_primary' => false,
        ]);

        // If this was the primary, promote another address
        if ($wasPrimary && $serverId) {
            $newPrimary = Address::where('server_id', $serverId)->first();
            if ($newPrimary) {
                $newPrimary->update(['is_primary' => true]);
            }
        }
    }

    /**
     * Get available addresses for a node.
     */
    public function getAvailable(Node $node): Collection
    {
        $pools = $node->addressPools()->with(['addresses' => function ($q) {
            $q->whereNull('server_id');
        }])->get();

        return $pools->flatMap(fn ($pool) => $pool->addresses->map(
            fn ($addr) => IpAddressData::fromModel($addr->setAttribute('pool', $pool))
        ));
    }

    /**
     * Set primary address for a server.
     */
    public function setPrimary(Server $server, Address $address): void
    {
        if ($address->server_id !== $server->id) {
            throw new \Exception('Address does not belong to this server');
        }

        // Remove primary flag from all other addresses
        $server->addresses()->where('id', '!=', $address->id)->update(['is_primary' => false]);

        // Set this one as primary
        $address->update(['is_primary' => true]);
    }

    /**
     * Get all addresses for a server.
     */
    public function getServerAddresses(Server $server): Collection
    {
        return $server->addresses->map(fn ($addr) => IpAddressData::fromModel($addr));
    }
}
