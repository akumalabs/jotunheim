<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { nodeApi, locationApi } from '@/api';
import type { Node } from '@/types/models';

import {
    PlusIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    XCircleIcon,
    TrashIcon,
    PencilIcon,
} from '@heroicons/vue/24/outline';

const queryClient = useQueryClient();

// Fetch nodes
const { data: nodes, isLoading, error } = useQuery({
    queryKey: ['admin', 'nodes'],
    queryFn: () => nodeApi.list(),
});

// Fetch locations for dropdown
const { data: locations } = useQuery({
    queryKey: ['admin', 'locations'],
    queryFn: () => locationApi.list(),
});

// Store connection status per node - persist to localStorage
const connectionStatus = reactive<Record<number, { success: boolean; message: string }>>({});

// Load from localStorage on mount
onMounted(() => {
    const saved = localStorage.getItem('midgard_node_status');
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            Object.assign(connectionStatus, parsed);
        } catch (e) {
            // ignore
        }
    }
});

// Save to localStorage whenever status changes
const saveStatus = () => {
    localStorage.setItem('midgard_node_status', JSON.stringify(connectionStatus));
};

// Modal state
const showModal = ref(false);
const editingNode = ref<Node | null>(null);
const formData = ref({
    name: '',
    fqdn: '',
    port: 8006,
    token_id: '',
    token_secret: '',
    location_id: '' as string | number,
    storage: 'local',
    network: 'vmbr0',
    cpu_overallocate: 0,
    memory_overallocate: 0,
    disk_overallocate: 0,
});
const formError = ref<string | null>(null);

const openCreate = () => {
    editingNode.value = null;
    formData.value = {
        name: '',
        fqdn: '',
        port: 8006,
        token_id: '',
        token_secret: '',
        location_id: '',
        storage: 'local',
        network: 'vmbr0',
        cpu_overallocate: 0,
        memory_overallocate: 0,
        disk_overallocate: 0,
    };
    formError.value = null;
    showModal.value = true;
};

const openEdit = (node: Node) => {
    editingNode.value = node;
    formData.value = {
        name: node.name,
        fqdn: node.fqdn,
        port: node.port,
        token_id: (node as any).token_id || '',
        token_secret: '', // Don't show existing secret
        location_id: (node as any).location_id || '',
        storage: node.storage || 'local',
        network: node.network || 'vmbr0',
        cpu_overallocate: node.cpu_overallocate || 0,
        memory_overallocate: node.memory_overallocate || 0,
        disk_overallocate: node.disk_overallocate || 0,
    };
    formError.value = null;
    showModal.value = true;
};

// Save mutation
const saveMutation = useMutation({
    mutationFn: async () => {
        const data: any = { ...formData.value };
        if (!data.token_secret) delete data.token_secret;
        if (!data.location_id) delete data.location_id;
        
        if (editingNode.value) {
            return nodeApi.update(editingNode.value.id, data);
        } else {
            return nodeApi.create(data);
        }
    },
    onSuccess: async (data: any) => {
        const isCreate = !editingNode.value;
        queryClient.invalidateQueries({ queryKey: ['admin', 'nodes'] });
        showModal.value = false;
        
        if (isCreate && data?.id) {
            // Auto sync resources for new node
            await syncNode(data);
        }
    },
    onError: (err: any) => {
        formError.value = err?.response?.data?.message || 'Failed to save node';
    },
});

const handleSubmit = () => {
    formError.value = null;
    saveMutation.mutate();
};

// Test connection mutation
const testingNode = ref<number | null>(null);

const testConnection = async (node: Node) => {
    testingNode.value = node.id;
    try {
        const result = await nodeApi.testConnection(node.id);
        connectionStatus[node.id] = { success: result.success, message: result.message };
        saveStatus();
    } catch (e: any) {
        connectionStatus[node.id] = { success: false, message: e?.message || 'Connection failed' };
        saveStatus();
    } finally {
        testingNode.value = null;
    }
};

// Sync resources mutation
const syncingNode = ref<number | null>(null);
const syncNode = async (node: Node) => {
    syncingNode.value = node.id;
    try {
        await nodeApi.sync(node.id);
        queryClient.invalidateQueries({ queryKey: ['admin', 'nodes'] });
        connectionStatus[node.id] = { success: true, message: 'Synced' };
        saveStatus();
    } catch (e: any) {
        connectionStatus[node.id] = { success: false, message: e?.message || 'Sync failed' };
        saveStatus();
    } finally {
        syncingNode.value = null;
    }
};





// Delete mutation
const deleteMutation = useMutation({
    mutationFn: (id: number) => nodeApi.delete(id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'nodes'] });
    },
});

const confirmDelete = (node: Node) => {
    if (confirm(`Are you sure you want to delete node "${node.name}"?`)) {
        deleteMutation.mutate(node.id);
    }
};

const formatBytes = (bytes: number): string => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <div class="p-6 space-y-6 animate-fade-in">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Nodes</h1>
                <p class="text-secondary-400">Manage your Proxmox nodes</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-5 h-5 mr-2" />
                Add Node
            </button>
        </div>

        <!-- Loading state -->
        <div v-if="isLoading" class="card card-body text-center py-12">
            <div class="animate-pulse text-secondary-400">Loading nodes...</div>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="card card-body text-center py-12 text-danger-500">
            Failed to load nodes
        </div>

        <!-- Empty state -->
        <div v-else-if="!nodes?.length" class="card card-body text-center py-12">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-secondary-800 flex items-center justify-center">
                <svg class="w-8 h-8 text-secondary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </div>
            <h3 class="text-lg font-medium text-white mb-2">No nodes yet</h3>
            <p class="text-secondary-400 mb-4">Get started by adding your first Proxmox node.</p>
            <button @click="openCreate" class="btn-primary mx-auto">
                <PlusIcon class="w-5 h-5 mr-2" />
                Add Your First Node
            </button>
        </div>

        <!-- Nodes table -->
        <div v-else class="card">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Location</th>
                            <th>FQDN</th>
                            <th>Resources</th>
                            <th>Config</th>
                            <th>Servers</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="node in nodes" :key="node.id">
                            <td class="font-medium text-white">{{ node.name }}</td>
                            <td>
                                <span v-if="node.location" class="badge-secondary">
                                    {{ node.location.short_code }}
                                </span>
                                <span v-else class="text-secondary-500">-</span>
                            </td>
                            <td>{{ node.fqdn }}:{{ node.port }}</td>
                            <td class="text-sm">
                                <div>CPU: {{ node.cpu || 0 }} cores</div>
                                <div>RAM: {{ formatBytes(node.memory) }}</div>
                                <div>Disk: {{ formatBytes(node.disk) }}</div>
                            </td>
                            <td class="text-sm">
                                <div>Storage: {{ node.storage || 'local' }}</div>
                                <div>Network: {{ node.network || 'vmbr0' }}</div>
                            </td>
                            <td>{{ node.servers_count ?? 0 }}</td>
                            <td>
                                <span v-if="node.maintenance_mode" class="badge-warning">
                                    Maintenance
                                </span>
                                <template v-else>
                                    <span v-if="connectionStatus[node.id]?.success" class="badge-success">
                                        <CheckCircleIcon class="w-4 h-4 mr-1" />
                                        Connected
                                    </span>
                                    <span v-else-if="connectionStatus[node.id] && !connectionStatus[node.id].success" class="badge-danger">
                                        <XCircleIcon class="w-4 h-4 mr-1" />
                                        Error
                                    </span>
                                    <span v-else class="badge-secondary">Not tested</span>
                                </template>
                            </td>
                            <td class="text-right space-x-1">
                                <button
                                    @click="testConnection(node)"
                                    :disabled="testingNode === node.id"
                                    class="btn-ghost btn-sm"
                                    title="Test Connection"
                                >
                                    <CheckCircleIcon v-if="testingNode !== node.id" class="w-4 h-4" />
                                    <ArrowPathIcon v-else class="w-4 h-4 animate-spin" />
                                </button>
                                <button
                                    @click="syncNode(node)"
                                    :disabled="syncingNode === node.id"
                                    class="btn-ghost btn-sm"
                                    title="Sync Resources"
                                >
                                    <ArrowPathIcon :class="['w-4 h-4', syncingNode === node.id && 'animate-spin']" />
                                </button>

                                <button @click="openEdit(node)" class="btn-ghost btn-sm" title="Edit">
                                    <PencilIcon class="w-4 h-4" />
                                </button>
                                <button
                                    @click="confirmDelete(node)"
                                    class="btn-ghost btn-sm text-danger-500 hover:text-danger-400"
                                    title="Delete"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
                <div class="card relative z-10 w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-white">
                            {{ editingNode ? 'Edit Node' : 'Add Node' }}
                        </h2>
                    </div>
                    <form @submit.prevent="handleSubmit" class="card-body space-y-4">
                        <!-- Error -->
                        <div v-if="formError" class="p-3 bg-danger-500/10 border border-danger-500/50 rounded text-danger-500 text-sm">
                            {{ formError }}
                        </div>

                        <!-- Name -->
                        <div>
                            <label class="label">Node Name</label>
                            <input v-model="formData.name" type="text" class="input" required placeholder="e.g. pve1" />
                        </div>

                        <!-- FQDN & Port -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2">
                                <label class="label">FQDN / IP Address</label>
                                <input v-model="formData.fqdn" type="text" class="input" required placeholder="pve1.example.com" />
                            </div>
                            <div>
                                <label class="label">Port</label>
                                <input v-model.number="formData.port" type="number" class="input" required />
                            </div>
                        </div>

                        <!-- API Token -->
                        <div>
                            <label class="label">API Token ID</label>
                            <input v-model="formData.token_id" type="text" class="input" required placeholder="root@pam!midgard" />
                            <p class="text-xs text-secondary-500 mt-1">Format: user@realm!tokenname</p>
                        </div>
                        <div>
                            <label class="label">API Token Secret {{ editingNode ? '(leave blank to keep current)' : '' }}</label>
                            <input v-model="formData.token_secret" type="password" class="input" :required="!editingNode" />
                        </div>

                        <!-- Location -->
                        <div>
                            <label class="label">Location (optional)</label>
                            <select v-model="formData.location_id" class="input">
                                <option value="">Select a location</option>
                                <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                    {{ loc.name }} ({{ loc.short_code }})
                                </option>
                            </select>
                        </div>

                        <!-- Storage & Network -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="label">Storage</label>
                                <input v-model="formData.storage" type="text" class="input" placeholder="local" />
                                <p class="text-xs text-secondary-500 mt-1">e.g. local, local-lvm, ceph</p>
                            </div>
                            <div>
                                <label class="label">Network Bridge</label>
                                <input v-model="formData.network" type="text" class="input" placeholder="vmbr0" />
                                <p class="text-xs text-secondary-500 mt-1">e.g. vmbr0, vmbr1</p>
                            </div>
                        </div>

                        <!-- Overallocation -->
                        <div>
                            <label class="label text-secondary-400 text-sm">Resource Overallocation (%)</label>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="label text-xs">CPU</label>
                                    <input v-model.number="formData.cpu_overallocate" type="number" class="input" min="0" max="500" />
                                </div>
                                <div>
                                    <label class="label text-xs">Memory</label>
                                    <input v-model.number="formData.memory_overallocate" type="number" class="input" min="0" max="500" />
                                </div>
                                <div>
                                    <label class="label text-xs">Disk</label>
                                    <input v-model.number="formData.disk_overallocate" type="number" class="input" min="0" max="500" />
                                </div>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showModal = false" class="btn-secondary flex-1">
                                Cancel
                            </button>
                            <button type="submit" :disabled="saveMutation.isPending.value" class="btn-primary flex-1">
                                {{ saveMutation.isPending.value ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>


    </div>
</template>
