<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { adminServerApi, nodeApi, userApi, templateApi, addressPoolApi } from '@/api';
import type { Server } from '@/types/models';
import type { TemplateGroup } from '@/api/templates';
import {
    PlusIcon,
    PlayIcon,
    StopIcon,
    ArrowPathIcon,
    EyeIcon,
    MagnifyingGlassIcon,
    ServerStackIcon,
    CpuChipIcon,
    GlobeAltIcon,
    UserIcon,
    KeyIcon,
    CircleStackIcon,
    CloudIcon,
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import ImportServersModal from './ImportServersModal.vue';

const queryClient = useQueryClient();

// Fetch servers
const { data: servers, isLoading } = useQuery({
    queryKey: ['admin', 'servers'],
    queryFn: () => adminServerApi.list(),
});

// Fetch nodes for dropdown
const { data: nodes } = useQuery({
    queryKey: ['admin', 'nodes'],
    queryFn: () => nodeApi.list(),
});

// Fetch users for dropdown
const { data: users } = useQuery({
    queryKey: ['admin', 'users'],
    queryFn: () => userApi.list(),
});

// Fetch address pools
const { data: addressPools } = useQuery({
    queryKey: ['admin', 'address-pools'],
    queryFn: () => addressPoolApi.list(),
});

// Search & Filtering
const searchQuery = ref('');
const statusFilter = ref('');

const filteredServers = computed(() => {
    if (!servers.value) return [];

    return servers.value.filter((server: Server) => {
        const matchesSearch =
            server.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            server.hostname?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            server.user?.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            server.addresses?.some(ip => ip.address.includes(searchQuery.value));

        const matchesStatus = statusFilter.value ? server.status === statusFilter.value : true;

        return matchesSearch && matchesStatus;
    });
});

// Modal state
const showModal = ref(false);
const showImportModal = ref(false);
const templateGroups = ref<TemplateGroup[]>([]);
const unmanagedVms = ref<any[]>([]);
const loadingTemplates = ref(false);

// Adoption Mode
const createMode = ref<'new' | 'adopt'>('new');

const formData = ref({
    name: '',
    hostname: '',
    password: '',
    user_id: '' as string | number,
    node_id: '' as string | number,
    vmid: '' as string | number, // Custom VM ID (Adopt: Existing, New: Optional)
    template_vmid: '',
    cpu: 1,
    memory: 1,      // GB
    disk: 10,       // GB
    bandwidth_limit: 0,  // GB (0 = unlimited)
    ip_address: '',
    address_pool_id: '' as string | number,
});
const formError = ref<string | null>(null);

// Watch for node changes
watch(() => formData.value.node_id, async (nodeId) => {
    if (nodeId && typeof nodeId === 'number') {
        loadingTemplates.value = true;

        // Reset dependent fields
        templateGroups.value = [];
        unmanagedVms.value = [];
        formData.value.template_vmid = '';
        if (createMode.value === 'adopt') {
            formData.value.vmid = '';
            autoFillVmid.value = '';
        }

        try {
            if (createMode.value === 'new') {
                templateGroups.value = await templateApi.listGroups(nodeId);
            } else {
                unmanagedVms.value = await nodeApi.getUnmanaged(nodeId);
            }
        } catch (e) {
            console.error(e);
        }
        loadingTemplates.value = false;
    } else {
        templateGroups.value = [];
        unmanagedVms.value = [];
    }
});

// Watch Mode Change
watch(createMode, async (mode) => {
    // Reload data if node is selected
    if (formData.value.node_id && typeof formData.value.node_id === 'number') {
        loadingTemplates.value = true;
        try {
            if (mode === 'new') {
                templateGroups.value = await templateApi.listGroups(formData.value.node_id);
            } else {
                unmanagedVms.value = await nodeApi.getUnmanaged(formData.value.node_id);
            }
        } catch (e) { }
        loadingTemplates.value = false;
    }

    // Clear relevant fields
    formData.value.template_vmid = '';
    formData.value.vmid = '';
});

const autoFillVmid = ref<string | number>('');

// Watch Unmanaged Selection to Auto-Fill
watch(autoFillVmid, (vmid) => {
    if (vmid) {
        const vm = unmanagedVms.value.find(v => v.vmid === vmid);
        if (vm) {
            formData.value.vmid = vm.vmid; // Fill VMID
            formData.value.name = vm.name;
            formData.value.cpu = vm.cpu;
            formData.value.memory = vm.memory; // Already GB
            formData.value.disk = vm.disk; // Already GB
        }
    }
});

const openImport = () => {
    showImportModal.value = true;
};

const openCreate = () => {
    formData.value = {
        name: '',
        hostname: '',
        password: '',
        user_id: '',
        node_id: '',
        vmid: '',
        template_vmid: '',
        cpu: 1,
        memory: 1,      // GB
        disk: 10,       // GB
        bandwidth_limit: 0,
        ip_address: '',
        address_pool_id: '',
    };
    templateGroups.value = [];
    unmanagedVms.value = [];
    formError.value = null;
    createMode.value = 'new'; // Reset to new
    showModal.value = true;
};

// Create mutation
const createMutation = useMutation({
    mutationFn: async () => {
        // Sanitize hostname
        const sanitizeHostname = (name: string): string => {
            return name
                .toLowerCase()
                .replace(/\s+/g, '.')
                .replace(/[^a-z0-9.-]/g, '')
                .replace(/\.+/g, '.')
                .replace(/^[.-]|[.-]$/g, '');
        };

        const hostname = formData.value.hostname
            ? sanitizeHostname(formData.value.hostname)
            : sanitizeHostname(formData.value.name);

        const data: any = {
            is_adoption: createMode.value === 'adopt', // Flag
            name: formData.value.name,
            hostname: hostname,
            password: formData.value.password, // Optional in adopt
            user_id: formData.value.user_id,
            node_id: formData.value.node_id,
            template_vmid: formData.value.template_vmid,
            cpu: formData.value.cpu,
            memory: formData.value.memory * 1024 * 1024 * 1024, // GB to bytes
            disk: formData.value.disk * 1024 * 1024 * 1024,     // GB to bytes
            bandwidth_limit: formData.value.bandwidth_limit ? formData.value.bandwidth_limit * 1024 * 1024 * 1024 * 1024 : null,
        };

        // VMID handling
        if (formData.value.vmid) {
            data.vmid = Number(formData.value.vmid);
        }

        // IP assignment
        if (formData.value.address_pool_id) {
            data.address_pool_id = formData.value.address_pool_id;
        }
        if (formData.value.ip_address) {
            data.ip_address = formData.value.ip_address;
        }
        return adminServerApi.create(data);
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers'] });
        showModal.value = false;
    },
    onError: (err: any) => {
        formError.value = err?.response?.data?.message || err?.response?.data?.error || 'Failed to create server';
    },
});

const handleSubmit = () => {
    formError.value = null;
    createMutation.mutate();
};

// Power mutation
const powerMutation = useMutation({
    mutationFn: ({ id, action }: { id: number; action: 'start' | 'stop' | 'restart' }) =>
        adminServerApi.power(id, action),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers'] });
    },
});

const handlePower = (server: Server, action: 'start' | 'stop' | 'restart') => {
    powerMutation.mutate({ id: server.id, action });
};

const getPrimaryIp = (server: Server) => {
    return server.addresses?.find(ip => ip.is_primary)?.address || server.addresses?.[0]?.address || '-';
};
</script>

<template>
    <div class="p-6 space-y-6 animate-fade-in">
        <!-- Header & Toolbar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white">Servers</h1>
                <p class="text-secondary-400">Manage infrastructure instances</p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <!-- Search -->
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-secondary-500" />
                    <input v-model="searchQuery" type="text" placeholder="Search servers..."
                        class="pl-9 pr-4 py-2 bg-secondary-900 border border-secondary-800 rounded-lg text-sm text-white focus:outline-none focus:border-primary-500 w-full sm:w-64" />
                </div>

                <!-- Import Button -->
                <button @click="openImport" class="btn-secondary">
                    <ArrowPathIcon class="w-5 h-5 mr-2" />
                    Sync / Import
                </button>

                <!-- Create Button -->
                <button @click="openCreate" class="btn-primary">
                    <PlusIcon class="w-5 h-5 mr-2" />
                    New Server
                </button>
            </div>
        </div>

        <!-- Import Modal -->
        <ImportServersModal :open="showImportModal" @close="showImportModal = false" />

        <!-- Servers Table Card -->
        <div class="bg-secondary-900/50 border border-secondary-800 rounded-xl overflow-hidden backdrop-blur-md">
            <!-- Table Header -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="bg-secondary-900/80 border-b border-secondary-800 text-xs text-secondary-400 uppercase tracking-wider">
                            <th class="px-6 py-4 font-medium w-16">Status</th>
                            <th class="px-6 py-4 font-medium">Name</th>
                            <th class="px-6 py-4 font-medium">Owner</th>
                            <th class="px-6 py-4 font-medium">Hypervisor</th>
                            <th class="px-6 py-4 font-medium">IP Address</th>
                            <th class="px-6 py-4 font-medium">Resources</th>
                            <th class="px-6 py-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary-800/50">
                        <tr v-if="isLoading">
                            <td colspan="7" class="px-6 py-12 text-center">
                                <LoadingSpinner text="white" />
                            </td>
                        </tr>
                        <tr v-else-if="filteredServers.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-secondary-400">
                                No servers found matching your criteria.
                            </td>
                        </tr>
                        <tr v-for="server in filteredServers" :key="server.id"
                            class="group hover:bg-secondary-800/30 transition-colors">
                            <!-- Status Pillar -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div v-if="server.status === 'running'" class="status-dot-running"></div>
                                    <div v-else-if="server.status === 'stopped'" class="status-dot-stopped"></div>
                                    <div v-else class="status-dot-pending"></div>
                                    <span class="text-xs font-medium uppercase" :class="{
                                        'text-success-400': server.status === 'running',
                                        'text-danger-400': server.status === 'stopped',
                                        'text-warning-400': ['pending', 'installing'].includes(server.status)
                                    }">{{ server.status }}</span>
                                </div>
                            </td>

                            <!-- Name & VMID -->
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span
                                        class="text-white font-medium group-hover:text-primary-400 transition-colors">{{
                                            server.name }}</span>
                                    <span class="text-xs text-secondary-500 font-mono">VM {{ server.vmid }}</span>
                                </div>
                            </td>

                            <!-- Owner -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-6 h-6 rounded-full bg-secondary-700 flex items-center justify-center text-xs text-secondary-300 font-bold">
                                        {{ server.user?.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="text-sm text-secondary-300">{{ server.user?.name }}</span>
                                </div>
                            </td>

                            <!-- Hypervisor (Node) -->
                            <td class="px-6 py-4 text-sm text-secondary-300">
                                {{ server.node?.name }}
                            </td>

                            <!-- IP Address -->
                            <td class="px-6 py-4 text-sm font-mono text-secondary-300">
                                {{ getPrimaryIp(server) }}
                            </td>

                            <!-- Resources -->
                            <td class="px-6 py-4">
                                <div class="flex gap-3 text-xs text-secondary-400">
                                    <span class="bg-secondary-800 px-2 py-1 rounded">{{ server.cpu }} CPU</span>
                                    <span class="bg-secondary-800 px-2 py-1 rounded">{{ server.memory_formatted
                                        }}</span>
                                    <span class="bg-secondary-800 px-2 py-1 rounded">{{ server.disk_formatted }}</span>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 text-right">
                                <div
                                    class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <template v-if="server.status === 'running'">
                                        <button @click="handlePower(server, 'stop')"
                                            class="p-1.5 text-danger-400 hover:bg-danger-500/10 rounded-lg transition-colors"
                                            title="Stop">
                                            <StopIcon class="w-4 h-4" />
                                        </button>
                                        <button @click="handlePower(server, 'restart')"
                                            class="p-1.5 text-secondary-400 hover:bg-secondary-800 rounded-lg transition-colors"
                                            title="Restart">
                                            <ArrowPathIcon class="w-4 h-4" />
                                        </button>
                                    </template>
                                    <button v-else @click="handlePower(server, 'start')"
                                        class="p-1.5 text-success-400 hover:bg-success-500/10 rounded-lg transition-colors"
                                        title="Start">
                                        <PlayIcon class="w-4 h-4" />
                                    </button>

                                    <RouterLink :to="`/admin/servers/${server.id}`"
                                        class="p-1.5 text-primary-400 hover:bg-primary-500/10 rounded-lg transition-colors"
                                        title="View Details">
                                        <EyeIcon class="w-4 h-4" />
                                    </RouterLink>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination (Placeholder) -->
            <div
                class="px-6 py-4 border-t border-secondary-800 flex items-center justify-between text-sm text-secondary-500">
                <span>Showing {{ filteredServers.length }} results</span>
                <div class="flex gap-2">
                    <button class="px-3 py-1 bg-secondary-800 rounded hover:bg-secondary-700 disabled:opacity-50"
                        disabled>Previous</button>
                    <button class="px-3 py-1 bg-secondary-800 rounded hover:bg-secondary-700 disabled:opacity-50"
                        disabled>Next</button>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div
                    class="bg-secondary-900 border border-secondary-800 rounded-xl relative z-10 w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
                    <div
                        class="px-6 py-4 border-b border-secondary-800 flex justify-between items-center bg-secondary-900 sticky top-0 z-10">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <PlusIcon class="w-5 h-5 text-primary-400" />
                            {{ createMode === 'new' ? 'Create Server' : 'Adopt Server' }}
                        </h2>
                        <button @click="showModal = false"
                            class="text-secondary-400 hover:text-white transition-colors">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="handleSubmit" class="p-6 space-y-8">
                        <!-- Mode Selector -->
                        <div class="flex bg-secondary-800 rounded-lg p-1">
                            <button type="button" @click="createMode = 'new'"
                                class="flex-1 py-1.5 text-sm font-medium rounded-md transition-all"
                                :class="createMode === 'new' ? 'bg-secondary-700 text-white shadow' : 'text-secondary-400 hover:text-white'">
                                <PlusIcon class="inline w-4 h-4 mr-1" /> Create New
                            </button>
                            <button type="button" @click="createMode = 'adopt'"
                                class="flex-1 py-1.5 text-sm font-medium rounded-md transition-all"
                                :class="createMode === 'adopt' ? 'bg-secondary-700 text-white shadow' : 'text-secondary-400 hover:text-white'">
                                <ArrowDownTrayIcon class="inline w-4 h-4 mr-1" /> Adopt Existing
                            </button>
                        </div>

                        <!-- Error -->
                        <div v-if="formError"
                            class="p-4 bg-danger-500/10 border border-danger-500/20 rounded-lg flex items-center gap-3 text-danger-400 text-sm">
                            <span class="bg-danger-500/20 p-1 rounded-full"><span
                                    class="w-2 h-2 bg-danger-500 rounded-full block"></span></span>
                            {{ formError }}
                        </div>

                        <!-- Basic Details -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Node (First for Adoption flow) -->
                            <div class="md:col-span-2">
                                <label class="label mb-1.5">Cluster Node</label>
                                <div class="relative">
                                    <CpuChipIcon
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-secondary-500" />
                                    <select v-model="formData.node_id" class="input pl-10" required>
                                        <option value="">Select Node</option>
                                        <option v-for="node in nodes" :key="node.id" :value="node.id">
                                            {{ node.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Unmanaged VM Selection (Optional Helper) -->
                            <div v-if="createMode === 'adopt'"
                                class="md:col-span-2 p-3 bg-secondary-800/50 border border-dashed border-secondary-700 rounded-lg mb-2">
                                <label class="label mb-1.5 text-xs">Auto-fill from Proxmox (Optional)</label>
                                <div class="relative">
                                    <ServerStackIcon
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-secondary-500" />
                                    <select v-model="autoFillVmid" class="input pl-10"
                                        :disabled="!formData.node_id || loadingTemplates">
                                        <option value="">{{ loadingTemplates ? 'Scanning...' : 'Select VM to auto-fill details...' }}</option>
                                        <option v-for="vm in unmanagedVms" :key="vm.vmid" :value="vm.vmid">
                                            {{ vm.vmid }} - {{ vm.name }} ({{ vm.cpu }}c / {{ vm.memory }}GB / {{
                                            vm.disk }}GB)
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="label mb-1.5">Server Name</label>
                                <div class="relative">
                                    <ServerStackIcon
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-secondary-500" />
                                    <input v-model="formData.name" type="text" class="input pl-10" required
                                        placeholder="my-server-01" />
                                </div>
                            </div>

                            <!-- VMID Input (Manual for both, but required/important for Adopt) -->
                            <div class="md:col-span-2" v-if="createMode === 'adopt'">
                                <label class="label mb-1.5">VM ID <span class="text-danger-400">*</span></label>
                                <div class="relative">
                                    <CpuChipIcon
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-secondary-500" />
                                    <input v-model.number="formData.vmid" type="number" class="input pl-10" required
                                        placeholder="100" />
                                </div>
                                <p class="text-[10px] text-secondary-500 mt-1">Must match the existing ID in Proxmox.
                                </p>
                            </div>

                            <div v-if="createMode === 'new'">
                                <label class="label mb-1.5">Hostname</label>
                                <div class="relative">
                                    <GlobeAltIcon
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-secondary-500" />
                                    <input v-model="formData.hostname" type="text" class="input pl-10"
                                        placeholder="server.example.com" />
                                </div>
                            </div>

                            <!-- Password field stays same -->
                            <div>
                                <label class="label mb-1.5">Root Password <span v-if="createMode === 'new'"
                                        class="text-danger-400">*</span></label>
                                <div class="relative">
                                    <KeyIcon
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-secondary-500" />
                                    <input v-model="formData.password" type="password" class="input pl-10"
                                        :required="createMode === 'new'" placeholder="••••••••" minlength="8" />
                                </div>
                            </div>
                        </div>

                        <hr class="border-secondary-800" />

                        <!-- Placement & Image -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" v-if="createMode === 'new'">
                            <!-- Node moved to top -->

                            <div class="md:col-span-2">
                                <label class="label mb-1.5">OS Template</label>
                                <div class="relative">
                                    <CircleStackIcon
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-secondary-500" />
                                    <select v-model="formData.template_vmid" class="input pl-10" required
                                        :disabled="!formData.node_id">
                                        <option value="">{{ formData.node_id ? 'Select Image' : 'Select Node first' }}
                                        </option>
                                        <optgroup v-for="group in templateGroups" :key="group.id" :label="group.name">
                                            <option v-for="template in group.templates" :key="template.id"
                                                :value="template.vmid">
                                                {{ template.name }}
                                            </option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="border-secondary-800" v-if="createMode === 'new'" />

                        <!-- Owner & Network -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="label mb-1.5">Owner</label>
                                <div class="relative">
                                    <UserIcon
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-secondary-500" />
                                    <select v-model="formData.user_id" class="input pl-10" required>
                                        <option value="">Select User</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">
                                            {{ user.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="label mb-1.5">Network Pool</label>
                                <div class="relative">
                                    <CloudIcon
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-secondary-500" />
                                    <select v-model="formData.address_pool_id" class="input pl-10">
                                        <option value="">No Auto-Assign</option>
                                        <option v-for="pool in addressPools" :key="pool.id" :value="pool.id">
                                            {{ pool.name }} ({{ pool.available_addresses || 0 }} free)
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="border-secondary-800" />

                        <!-- Resources -->
                        <div>
                            <h4 class="text-xs font-bold text-secondary-400 uppercase tracking-wider mb-4">Resource
                                Limits</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="label mb-1.5">vCPU</label>
                                    <input v-model.number="formData.cpu" type="number" class="input" min="1" max="64" />
                                </div>
                                <div>
                                    <label class="label mb-1.5">RAM (GB)</label>
                                    <input v-model.number="formData.memory" type="number" class="input" min="1" />
                                </div>
                                <div>
                                    <label class="label mb-1.5">Disk (GB)</label>
                                    <input v-model.number="formData.disk" type="number" class="input" min="10" />
                                </div>
                                <div>
                                    <label class="label mb-1.5">Bandwidth (TB)</label>
                                    <input v-model.number="formData.bandwidth_limit" type="number" class="input"
                                        placeholder="Unlimited" min="0" />
                                    <div class="text-[10px] text-secondary-500 mt-1 pl-1">0 for unlimited</div>
                                </div>
                            </div>
                            <!-- Custom VMID for Create New -->
                            <div class="mt-4" v-if="createMode === 'new'">
                                <label class="label mb-1.5">Custom VMID <span
                                        class="text-secondary-500 text-xs font-normal">(Optional)</span></label>
                                <input v-model.number="formData.vmid" type="number" class="input"
                                    placeholder="Auto-assign" />
                            </div>
                        </div>

                        <!-- Footer -->
                        <div
                            class="flex gap-3 pt-6 border-t border-secondary-800 sticky bottom-0 bg-secondary-900 pb-2 z-10">
                            <button type="button" @click="showModal = false" class="btn-secondary w-1/3">
                                Cancel
                            </button>
                            <button type="submit" :disabled="createMutation.isPending.value"
                                class="btn-primary flex-1 shadow-lg shadow-primary-500/20">
                                <span v-if="createMutation.isPending.value"
                                    class="flex items-center justify-center gap-2">
                                    <LoadingSpinner size="sm" color="white" /> {{ createMode === 'adopt' ?
                                        'Importing...' :
                                        'Deploying...' }}
                                </span>
                                <span v-else>{{ createMode === 'adopt' ? 'Adopt Server' : 'Deploy Server' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
