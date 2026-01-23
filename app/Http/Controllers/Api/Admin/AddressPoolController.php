<?php

namespace App\Http\Controllers\Api\Admin;

use App\Data\Ipam\AddressPoolData;
use App\Data\Ipam\IpAddressData;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\AddressPool;
use App\Models\Node;
use App\Services\Ipam\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressPoolController extends Controller
{
    /**
     * List all address pools.
     */
    public function index(): JsonResponse
    {
        $pools = AddressPool::withCount(['addresses', 'nodes'])
            ->with('nodes:id,name')
            ->get()
            ->map(fn ($pool) => AddressPoolData::fromModel($pool));

        return response()->json([
            'data' => $pools,
        ]);
    }

    /**
     * Get a single pool with addresses.
     * Returns detailed pool data with IpAddressData DTOs.
     */
    public function show(AddressPool $address_pool): JsonResponse
    {
        $address_pool->load(['nodes:id,name', 'addresses.server:id,uuid,name']);

        $nodes = $address_pool->nodes->map(fn ($n) => ['id' => $n->id, 'name' => $n->name]);
        $addresses = $address_pool->addresses->map(fn ($addr) => IpAddressData::fromModel($addr));

        return response()->json([
            'data' => [
                ...AddressPoolData::fromModel($address_pool)->toArray(),
                'addresses' => $addresses,
                'nodes' => $nodes,
            ],
        ]);
    }

    /**
     * Create an address pool.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'node_ids' => ['sometimes', 'array'],
            'node_ids.*' => ['exists:nodes,id'],
        ]);

        $pool = AddressPool::create(['name' => $validated['name']]);

        if (! empty($validated['node_ids'])) {
            $pool->nodes()->attach($validated['node_ids']);
        }

        $pool->load('nodes:id,name');

        return response()->json([
            'message' => 'Address pool created',
            'data' => $this->formatPool($pool, true),
        ], 201);
    }

    /**
     * Update an address pool.
     */
    public function update(Request $request, AddressPool $address_pool): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'node_ids' => ['sometimes', 'array'],
            'node_ids.*' => ['exists:nodes,id'],
        ]);

        if (isset($validated['name'])) {
            $address_pool->update(['name' => $validated['name']]);
        }

        if (isset($validated['node_ids'])) {
            $address_pool->nodes()->sync($validated['node_ids']);
        }

        return response()->json([
            'message' => 'Address pool updated',
            'data' => $this->formatPool($address_pool->fresh()->load('nodes:id,name')),
        ]);
    }

    /**
     * Delete an address pool.
     */
    public function destroy(AddressPool $address_pool): JsonResponse
    {
        // Check if any addresses are assigned
        if ($address_pool->addresses()->whereNotNull('server_id')->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete pool with assigned addresses',
            ], 422);
        }

        $address_pool->delete();

        return response()->json([
            'message' => 'Address pool deleted',
        ]);
    }

    /**
     * Add addresses to a pool (bulk).
     */
    public function addAddresses(Request $request, AddressPool $address_pool): JsonResponse
    {
        $validated = $request->validate([
            'addresses' => ['required', 'array', 'min:1'],
            'addresses.*.address' => ['required', 'ip'],
            'addresses.*.cidr' => ['required', 'integer', 'min:1', 'max:128'],
            'addresses.*.gateway' => ['required', 'ip'],
            'addresses.*.type' => ['sometimes', 'in:ipv4,ipv6'],
            'addresses.*.mac_address' => ['nullable', 'string'],
        ]);

        $created = [];
        foreach ($validated['addresses'] as $addrData) {
            $address = $address_pool->addresses()->create([
                'address' => $addrData['address'],
                'cidr' => $addrData['cidr'],
                'gateway' => $addrData['gateway'],
                'type' => $addrData['type'] ?? 'ipv4',
                'mac_address' => $addrData['mac_address'] ?? null,
            ]);
            $created[] = $address->address;
        }

        return response()->json([
            'message' => count($created).' addresses added',
            'data' => ['addresses' => $created],
        ], 201);
    }

    /**
     * Add address range (e.g., 192.168.1.10-192.168.1.20).
     */
    public function addRange(Request $request, AddressPool $address_pool): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'ipv4'],
            'end' => ['required', 'ipv4'],
            'cidr' => ['required', 'integer', 'min:1', 'max:32'],
            'gateway' => ['required', 'ipv4'],
        ]);

        $start = ip2long($validated['start']);
        $end = ip2long($validated['end']);

        if ($start > $end) {
            return response()->json([
                'message' => 'Start IP must be less than or equal to end IP',
            ], 422);
        }

        if ($end - $start > 255) {
            return response()->json([
                'message' => 'Maximum 256 addresses per range',
            ], 422);
        }

        $created = 0;
        for ($ip = $start; $ip <= $end; $ip++) {
            $address = long2ip($ip);

            // Skip if already exists
            if (Address::where('address', $address)->exists()) {
                continue;
            }

            $address_pool->addresses()->create([
                'address' => $address,
                'cidr' => $validated['cidr'],
                'gateway' => $validated['gateway'],
                'type' => 'ipv4',
            ]);
            $created++;
        }

        return response()->json([
            'message' => "{$created} addresses created",
            'data' => [
                'range' => "{$validated['start']} - {$validated['end']}",
                'created' => $created,
            ],
        ], 201);
    }

    /**
     * Delete an address.
     */
    public function destroyAddress(Address $address): JsonResponse
    {
        if ($address->server_id) {
            return response()->json([
                'message' => 'Cannot delete assigned address',
            ], 422);
        }

        $address->delete();

        return response()->json([
            'message' => 'Address deleted',
        ]);
    }

    /**
     * Get available addresses for a node.
     */
    public function available(Node $node, AddressService $addressService): JsonResponse
    {
        $addresses = $addressService->getAvailable($node);

        return response()->json([
            'data' => $addresses,
        ]);
    }

    /**
     * Format pool for response.
     */
    protected function formatPool(AddressPool $pool, bool $detailed = false, bool $loaded = false): array
    {
        $data = [
            'id' => $pool->id,
            'name' => $pool->name,
            'addresses_count' => $pool->addresses_count ?? ($loaded ? count($pool->addresses) : $pool->addresses->count()),
            'available_count' => $pool->available_count ?? ($loaded ? $pool->addresses->whereNull('server_id')->count() : $pool->addresses->whereNull('server_id')->count()),
            'nodes' => $loaded ? $pool->nodes->map(fn ($n) => ['id' => $n->id, 'name' => $n->name]) : $pool->nodes->map(fn ($n) => ['id' => $n->id, 'name' => $n->name]),
            'created_at' => $pool->created_at,
        ];

        if ($detailed) {
            $data['addresses'] = $pool->addresses->map(fn ($addr) => [
                'id' => $addr->id,
                'address' => $addr->address,
                'cidr' => $addr->cidr,
                'gateway' => $addr->gateway,
                'type' => $addr->type,
                'mac_address' => $addr->mac_address,
                'is_primary' => $addr->is_primary,
                'server' => $addr->server ? [
                    'id' => $addr->server->id,
                    'uuid' => $addr->server->uuid,
                    'name' => $addr->server->name,
                ] : null,
            ]);
        }

        return $data;
    }
}
