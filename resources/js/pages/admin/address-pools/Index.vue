<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { addressPoolApi, nodeApi, adminServerApi } from '@/api';
import type { AddressPool, Address } from '@/api/addressPools';
import {
    PlusIcon,
    GlobeAltIcon,
    MagnifyingGlassIcon,
    EllipsisVerticalIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';

const queryClient = useQueryClient();

// Current view: 'list' or 'detail'
const currentView = ref<'list' | 'detail'>('list');
const selectedPool = ref<AddressPool | null>(null);

// Fetch pools
const { data: pools, isLoading } = useQuery({
    queryKey: ['admin', 'address-pools'],
    queryFn: () => addressPoolApi.list(),
});

// Fetch nodes for assignment
const { data: nodes } = useQuery({
    queryKey: ['admin', 'nodes'],
    queryFn: () => nodeApi.list(),
});

// Fetch servers for assignment
const { data: servers } = useQuery({
    queryKey: ['admin', 'servers'],
    queryFn: () => adminServerApi.list(),
});

// Pool detail data
const poolDetail = ref<AddressPool | null>(null);
const poolAddresses = ref<Address[]>([]);
const loadingDetail = ref(false);
const searchQuery = ref('');

// Action menus
const openMenuId = ref<number | null>(null);
const addressMenuId = ref<number | null>(null);

// Create Pool Modal
const showPoolModal = ref(false);
const editingPool = ref<AddressPool | null>(null);
const poolFormData = ref({
    name: '',
    node_ids: [] as number[],
});
const poolFormError = ref<string | null>(null);

// Create/Edit Address Modal
const showAddressModal = ref(false);
const editingAddress = ref<Address | null>(null);
const addressMode = ref<'single' | 'multiple'>('single');
const addressFormData = ref({
    address: '',
    cidr: 24,
    gateway: '',
    type: 'ipv4',
    server_id: '' as string | number,
    // For multiple mode
    start: '',
    end: '',
});
const addressFormError = ref<string | null>(null);

// Open pool detail view
const openPoolDetail = async (pool: AddressPool) => {
    selectedPool.value = pool;
    currentView.value = 'detail';
    loadingDetail.value = true;
    openMenuId.value = null;
    
    try {
        const detail = await addressPoolApi.get(pool.id);
        poolDetail.value = detail;
        poolAddresses.value = detail.addresses || [];
    } catch (e) {
        poolAddresses.value = [];
    }
    loadingDetail.value = false;
};

// Back to list
const backToList = () => {
    currentView.value = 'list';
    selectedPool.value = null;
    poolDetail.value = null;
    poolAddresses.value = [];
};

// Filtered addresses
const filteredAddresses = () => {
    if (!searchQuery.value) return poolAddresses.value;
    const q = searchQuery.value.toLowerCase();
    return poolAddresses.value.filter(a => 
        a.address.toLowerCase().includes(q) ||
        a.gateway?.toLowerCase().includes(q)
    );
};

// Pool Modal
const openCreatePool = () => {
    editingPool.value = null;
    poolFormData.value = { name: '', node_ids: [] };
    poolFormError.value = null;
    showPoolModal.value = true;
    openMenuId.value = null;
};

const openEditPool = (pool: AddressPool) => {
    editingPool.value = pool;
    poolFormData.value = {
        name: pool.name,
        node_ids: pool.nodes?.map((n: any) => n.id) || [],
    };
    poolFormError.value = null;
    showPoolModal.value = true;
    openMenuId.value = null;
};

// Address Modal
const openCreateAddress = () => {
    editingAddress.value = null;
    addressMode.value = 'single';
    addressFormData.value = {
        address: '',
        cidr: 24,
        gateway: '',
        type: 'ipv4',
        server_id: '',
        start: '',
        end: '',
    };
    addressFormError.value = null;
    showAddressModal.value = true;
    addressMenuId.value = null;
};

const openEditAddress = (address: Address) => {
    editingAddress.value = address;
    addressMode.value = 'single';
    addressFormData.value = {
        address: address.address,
        cidr: address.cidr,
        gateway: address.gateway,
        type: address.type || 'ipv4',
        server_id: address.server_id || '',
        start: '',
        end: '',
    };
    addressFormError.value = null;
    showAddressModal.value = true;
    addressMenuId.value = null;
};

// Mutations
const poolMutation = useMutation({
    mutationFn: async () => {
        const data = {
            name: poolFormData.value.name,
            node_ids: poolFormData.value.node_ids,
        };
        if (editingPool.value) {
            return addressPoolApi.update(editingPool.value.id, data);
        }
        return addressPoolApi.create(data);
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'address-pools'] });
        showPoolModal.value = false;
    },
    onError: (err: any) => {
        poolFormError.value = err?.response?.data?.message || 'Failed to save pool';
    },
});

const addressMutation = useMutation({
    mutationFn: async () => {
        if (!selectedPool.value) return;
        
        if (editingAddress.value) {
            // Update single address - need backend endpoint
            // For now, just refresh
            return;
        }
        
        if (addressMode.value === 'multiple') {
            // Add range
            return addressPoolApi.addRange(selectedPool.value.id, {
                start: addressFormData.value.start,
                end: addressFormData.value.end,
                cidr: addressFormData.value.cidr,
                gateway: addressFormData.value.gateway,
            });
        } else {
            // Add single address
            return addressPoolApi.addAddresses(selectedPool.value.id, [{
                address: addressFormData.value.address,
                cidr: addressFormData.value.cidr,
                gateway: addressFormData.value.gateway,
                type: addressFormData.value.type,
            }]);
        }
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'address-pools'] });
        showAddressModal.value = false;
        if (selectedPool.value) openPoolDetail(selectedPool.value);
    },
    onError: (err: any) => {
        addressFormError.value = err?.response?.data?.message || 'Failed to save address';
    },
});

const deletePoolMutation = useMutation({
    mutationFn: (id: number) => addressPoolApi.delete(id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'address-pools'] });
        openMenuId.value = null;
    },
});

const deleteAddressMutation = useMutation({
    mutationFn: (id: number) => addressPoolApi.deleteAddress(id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'address-pools'] });
        if (selectedPool.value) openPoolDetail(selectedPool.value);
        addressMenuId.value = null;
    },
});

const confirmDeletePool = (pool: AddressPool) => {
    if (confirm(`Delete pool "${pool.name}"?`)) {
        deletePoolMutation.mutate(pool.id);
    }
};

const confirmDeleteAddress = (address: Address) => {
    if (confirm(`Delete address ${address.address}?`)) {
        deleteAddressMutation.mutate(address.id);
    }
};



// Close dropdowns on click outside
const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.dropdown-menu') && !target.closest('.dropdown-trigger')) {
        openMenuId.value = null;
        addressMenuId.value = null;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="p-6 space-y-6 animate-fade-in">
        <!-- Pool List View -->
        <template v-if="currentView === 'list'">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">IPAM</h1>
                    <p class="text-secondary-400">IP Address Management</p>
                </div>
                <button @click="openCreatePool" class="btn-primary">
                    <PlusIcon class="w-5 h-5 mr-2" />
                    Create Pool
                </button>
            </div>

            <!-- Loading -->
            <div v-if="isLoading" class="card card-body text-center py-12">
                <div class="animate-pulse text-secondary-400">Loading pools...</div>
            </div>

            <!-- Empty state -->
            <div v-else-if="!pools?.length" class="card card-body text-center py-12">
                <GlobeAltIcon class="w-12 h-12 mx-auto mb-4 text-secondary-500" />
                <h3 class="text-lg font-medium text-white mb-2">No IP pools</h3>
                <p class="text-secondary-400 mb-4">Create a pool to manage IP addresses.</p>
                <button @click="openCreatePool" class="btn-primary mx-auto">
                    <PlusIcon class="w-5 h-5 mr-2" />
                    Create Pool
                </button>
            </div>

            <!-- Pool Table -->
            <div v-else class="card overflow-visible">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>NAME</th>
                                <th class="text-center">NODES</th>
                                <th class="text-center">ADDRESSES</th>
                                <th class="w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="pool in pools" 
                                :key="pool.id" 
                                class="hover:bg-secondary-800/50 cursor-pointer"
                                @click="openPoolDetail(pool)"
                            >
                                <td class="font-medium text-white">{{ pool.name }}</td>
                                <td class="text-center">{{ pool.nodes?.length || 0 }}</td>
                                <td class="text-center">{{ pool.total_addresses || 0 }}</td>
                                <td class="text-right relative" @click.stop>
                                    <button 
                                        @click="openMenuId = openMenuId === pool.id ? null : pool.id"
                                        class="btn-ghost btn-sm dropdown-trigger"
                                    >
                                        <EllipsisVerticalIcon class="w-5 h-5" />
                                    </button>
                                    <div 
                                        v-if="openMenuId === pool.id" 
                                        class="dropdown-menu absolute right-0 top-full mt-1 bg-secondary-800 border border-secondary-700 rounded-lg shadow-lg z-50 py-1 min-w-32"
                                    >
                                        <button @click="openEditPool(pool)" class="w-full text-left px-4 py-2 hover:bg-secondary-700 text-white text-sm">
                                            Edit
                                        </button>
                                        <button @click="confirmDeletePool(pool)" class="w-full text-left px-4 py-2 hover:bg-secondary-700 text-danger-500 text-sm">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Pool Detail View -->
        <template v-else-if="currentView === 'detail' && selectedPool">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm">
                <button @click="backToList" class="text-secondary-400 hover:text-white flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" />
                    IPAM
                </button>
                <span class="text-secondary-500">&gt;</span>
                <span class="text-white font-medium">{{ selectedPool.name }}</span>
            </div>

            <!-- Search + Create -->
            <div class="flex items-center justify-between gap-4">
                <div class="relative flex-1 max-w-md">
                    <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-secondary-400" />
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        class="input pl-10" 
                        placeholder="Search..."
                    />
                </div>
                <button @click="openCreateAddress" class="btn-primary">
                    <PlusIcon class="w-5 h-5 mr-2" />
                    Create Address
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loadingDetail" class="card card-body text-center py-12">
                <div class="animate-pulse text-secondary-400">Loading addresses...</div>
            </div>

            <!-- Empty -->
            <div v-else-if="!poolAddresses.length" class="card card-body text-center py-12">
                <p class="text-secondary-400 mb-4">No addresses in this pool</p>
                <button @click="openCreateAddress" class="btn-primary mx-auto">
                    <PlusIcon class="w-5 h-5 mr-2" />
                    Create Address
                </button>
            </div>

            <!-- Address Table -->
            <div v-else class="card overflow-visible">
                <div class="overflow-x-auto overflow-y-visible">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ADDRESS</th>
                                <th>CIDR</th>
                                <th>GATEWAY</th>
                                <th>TYPE</th>
                                <th>SERVER</th>
                                <th class="w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="addr in filteredAddresses()" :key="addr.id">
                                <td>{{ addr.address }}</td>
                                <td>{{ addr.cidr }}</td>
                                <td class="text-secondary-300">{{ addr.gateway }}</td>
                                <td class="uppercase">{{ addr.type }}</td>
                                <td>
                                    <span v-if="addr.server" class="text-primary-400">{{ addr.server.name }}</span>
                                    <span v-else class="text-secondary-500">-</span>
                                </td>
                                <td class="text-right relative">
                                    <button 
                                        @click="addressMenuId = addressMenuId === addr.id ? null : addr.id"
                                        class="btn-ghost btn-sm dropdown-trigger"
                                    >
                                        <EllipsisVerticalIcon class="w-5 h-5" />
                                    </button>
                                    <div 
                                        v-if="addressMenuId === addr.id" 
                                        class="dropdown-menu absolute right-0 top-full mt-1 bg-secondary-800 border border-secondary-700 rounded-lg shadow-lg z-50 py-1 min-w-32"
                                    >
                                        <button @click="openEditAddress(addr)" class="w-full text-left px-4 py-2 hover:bg-secondary-700 text-white text-sm">
                                            Edit
                                        </button>
                                        <button @click="confirmDeleteAddress(addr)" class="w-full text-left px-4 py-2 hover:bg-secondary-700 text-danger-500 text-sm">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Create/Edit Pool Modal -->
        <Teleport to="body">
            <div v-if="showPoolModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showPoolModal = false"></div>
                <div class="card relative z-10 w-full max-w-md">
                    <div class="card-header text-center">
                        <h2 class="text-lg font-semibold text-white">
                            {{ editingPool ? 'Edit Pool' : 'Create Pool' }}
                        </h2>
                    </div>
                    <form @submit.prevent="poolMutation.mutate()" class="card-body space-y-4">
                        <div v-if="poolFormError" class="p-3 bg-danger-500/10 border border-danger-500/50 rounded text-danger-500 text-sm">
                            {{ poolFormError }}
                        </div>

                        <div>
                            <label class="label">Name</label>
                            <input v-model="poolFormData.name" type="text" class="input" required placeholder="e.g. Public IPv4" />
                        </div>

                        <div v-if="nodes?.length">
                            <label class="label">Node</label>
                            <select 
                                :value="poolFormData.node_ids[0]"
                                @change="(e) => poolFormData.node_ids = [(e.target as HTMLSelectElement).value ? parseInt((e.target as HTMLSelectElement).value) : 0].filter(id => id)"
                                class="input"
                                required
                            >
                                <option value="">Select a node</option>
                                <option v-for="node in nodes" :key="node.id" :value="node.id">
                                    {{ node.name }} ({{ node.fqdn }})
                                </option>
                            </select>
                            <p class="text-xs text-secondary-500 mt-1">IP Pools are currently assigned to a single node.</p>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showPoolModal = false" class="btn-secondary flex-1 text-sm py-2.5">Cancel</button>
                            <button type="submit" :disabled="poolMutation.isPending.value" class="btn-primary flex-1 text-sm py-2.5">
                                {{ editingPool ? 'Save' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Create/Edit Address Modal -->
        <Teleport to="body">
            <div v-if="showAddressModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showAddressModal = false"></div>
                <div class="card relative z-10 w-full max-w-md">
                    <div class="card-header text-center">
                        <h2 class="text-lg font-semibold text-white">
                            {{ editingAddress ? 'Editing Address' : 'Create Address' }}
                        </h2>
                    </div>
                    <form @submit.prevent="addressMutation.mutate()" class="card-body space-y-4">
                        <div v-if="addressFormError" class="p-3 bg-danger-500/10 border border-danger-500/50 rounded text-danger-500 text-sm">
                            {{ addressFormError }}
                        </div>

                        <!-- Mode tabs (only for create) -->
                        <div v-if="!editingAddress" class="flex gap-2">
                            <button 
                                type="button" 
                                @click="addressMode = 'single'" 
                                :class="['btn-sm flex-1', addressMode === 'single' ? 'btn-primary' : 'btn-secondary']"
                            >
                                Single
                            </button>
                            <button 
                                type="button" 
                                @click="addressMode = 'multiple'" 
                                :class="['btn-sm flex-1', addressMode === 'multiple' ? 'btn-primary' : 'btn-secondary']"
                            >
                                Multiple
                            </button>
                        </div>

                        <!-- IPv4/IPv6 selection -->
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="addressFormData.type" value="ipv4" class="text-primary-500" />
                                <span class="text-white">IPv4</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="addressFormData.type" value="ipv6" class="text-primary-500" />
                                <span class="text-white">IPv6</span>
                            </label>
                        </div>

                        <!-- Single mode -->
                        <div v-if="addressMode === 'single'">
                            <label class="label">Address</label>
                            <input v-model="addressFormData.address" type="text" class="input" required placeholder="10.10.10.1" />
                        </div>

                        <!-- Multiple mode -->
                        <template v-if="addressMode === 'multiple' && !editingAddress">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Start IP</label>
                                    <input v-model="addressFormData.start" type="text" class="input" required placeholder="10.10.10.1" />
                                </div>
                                <div>
                                    <label class="label">End IP</label>
                                    <input v-model="addressFormData.end" type="text" class="input" required placeholder="10.10.10.50" />
                                </div>
                            </div>
                        </template>

                        <div>
                            <label class="label">CIDR</label>
                            <input v-model.number="addressFormData.cidr" type="number" class="input" min="1" max="128" required />
                        </div>

                        <div>
                            <label class="label">Gateway</label>
                            <input v-model="addressFormData.gateway" type="text" class="input" required placeholder="10.10.10.1" />
                        </div>

                        <!-- Assigned Server (only for edit) -->
                        <div v-if="editingAddress">
                            <label class="label">Assigned Server</label>
                            <select v-model="addressFormData.server_id" class="input">
                                <option value="">None</option>
                                <option v-for="server in servers" :key="server.id" :value="server.id">
                                    {{ server.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showAddressModal = false" class="btn-secondary flex-1 text-sm py-2.5">Cancel</button>
                            <button type="submit" :disabled="addressMutation.isPending.value" class="btn-primary flex-1 text-sm py-2.5">
                                {{ editingAddress ? 'Save' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
