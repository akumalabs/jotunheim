<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { nodeApi, userApi, adminServerApi } from '@/api';
import {
    ArrowPathIcon,
    XMarkIcon,
    ServerStackIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits(['close', 'imported']);

const queryClient = useQueryClient();

const unmanagedServers = ref<any[]>([]);
const selectedVmids = ref<Set<string>>(new Set());
const selectedUserId = ref<number | ''>('');
const isScanning = ref(false);
const error = ref<string | null>(null);

const { data: users } = useQuery({
    queryKey: ['admin', 'users'],
    queryFn: () => userApi.list(),
});

const scanNodes = async () => {
    isScanning.value = true;
    error.value = null;
    unmanagedServers.value = [];
    selectedVmids.value.clear();

    try {
        const nodes = await nodeApi.list();

        const promises = nodes.map(async (node) => {
            try {
                const vms = await nodeApi.getUnmanaged(node.id);
                return vms.map(vm => ({
                    ...vm,
                    nodeId: node.id,
                    nodeName: node.name,
                    uniqueId: `${node.id}-${vm.vmid}`
                }));
            } catch (e) {
                console.error(`Failed to scan node ${node.name}`, e);
                return [];
            }
        });

        const results = await Promise.all(promises);
        unmanagedServers.value = results.flat();
    } catch (e: any) {
        error.value = "Failed to scan nodes: " + (e.message || 'Unknown error');
    } finally {
        isScanning.value = false;
    }
};

onMounted(() => {
    if (props.open) scanNodes();
});

const toggleSelection = (uniqueId: string) => {
    if (selectedVmids.value.has(uniqueId)) {
        selectedVmids.value.delete(uniqueId);
    } else {
        selectedVmids.value.add(uniqueId);
    }
};

const toggleAll = () => {
    if (selectedVmids.value.size === unmanagedServers.value.length) {
        selectedVmids.value.clear();
    } else {
        unmanagedServers.value.forEach(s => selectedVmids.value.add(s.uniqueId));
    }
};

const importMutation = useMutation({
    mutationFn: async () => {
        if (!selectedUserId.value) throw new Error("Please select an owner.");

        const serversToImport = unmanagedServers.value.filter(s => selectedVmids.value.has(s.uniqueId));

        const promises = serversToImport.map(vm => {
            return adminServerApi.create({
                is_adoption: true,
                name: vm.name,
                hostname: vm.name.toLowerCase().replace(/[^a-z0-9.-]/g, '-'),
                user_id: Number(selectedUserId.value),
                node_id: vm.nodeId,
                vmid: vm.vmid,
                cpu: vm.cpu,
                memory: Math.round(vm.memory_gb * 1024 * 1024 * 1024),
                disk: Math.round(vm.disk_gb * 1024 * 1024 * 1024),
                bandwidth_limit: 0,
            });
        });

        await Promise.all(promises);
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers'] });
        emit('imported');
        emit('close');
    },
    onError: (e: any) => {
        error.value = e.message || "Failed to import servers";
    },
});

const handleImport = () => {
    importMutation.mutate();
};
</script>

<template>
    <TransitionRoot as="template" :show="open">
        <Dialog as="div" class="relative z-50" @close="$emit('close')">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100" leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform overflow-hidden rounded-xl bg-secondary-900 border border-secondary-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl">

                            <div class="border-b border-secondary-800 px-6 py-4 flex items-center justify-between">
                                <DialogTitle as="h3"
                                    class="text-lg font-semibold leading-6 text-white flex items-center gap-2">
                                    <ArrowPathIcon class="w-5 h-5 text-primary-400"
                                        :class="{ 'animate-spin': isScanning }" />
                                    Import Unmanaged Servers
                                </DialogTitle>
                                <div class="flex items-center gap-2">
                                    <button @click="scanNodes"
                                        class="p-1 text-secondary-400 hover:text-white transition-colors"
                                        title="Rescan">
                                        <ArrowPathIcon class="w-5 h-5" />
                                    </button>
                                    <button @click="$emit('close')"
                                        class="p-1 text-secondary-400 hover:text-white transition-colors">
                                        <XMarkIcon class="w-6 h-6" />
                                    </button>
                                </div>
                            </div>

                            <div class="px-6 py-6">
                                <div v-if="error"
                                    class="mb-4 p-4 bg-danger-500/10 border border-danger-500/20 rounded-lg text-danger-400 text-sm">
                                    {{ error }}
                                </div>

                                <div v-if="isScanning"
                                    class="py-12 flex flex-col items-center justify-center text-secondary-400">
                                    <LoadingSpinner class="mb-3" />
                                    <p>Scanning cluster nodes for unmanaged VMs...</p>
                                </div>

                                <div v-else-if="unmanagedServers.length === 0"
                                    class="py-12 text-center text-secondary-400">
                                    <ServerStackIcon class="w-12 h-12 mx-auto mb-3 opacity-20" />
                                    <p>No unmanaged servers found on any node.</p>
                                    <p class="text-sm mt-1 text-secondary-500">All VMs are already imported or nodes are unreachable.</p>
                                </div>

                                <div v-else class="space-y-4">
                                    <p class="text-sm text-secondary-400">Found {{ unmanagedServers.length }} unmanaged VMs. Select servers to import.</p>

                                    <div class="border border-secondary-800 rounded-lg overflow-hidden bg-secondary-900/50">
                                        <table class="w-full text-left text-sm">
                                            <thead class="bg-secondary-800/50 text-secondary-400 font-medium">
                                                <tr>
                                                    <th class="p-3 w-10">
                                                        <input type="checkbox"
                                                            :checked="selectedVmids.size === unmanagedServers.length && unmanagedServers.length > 0"
                                                            @click="toggleAll"
                                                            class="rounded border-secondary-600 bg-secondary-800 text-primary-500 focus:ring-primary-500/30" />
                                                    </th>
                                                    <th class="p-3">VMID</th>
                                                    <th class="p-3">Name</th>
                                                    <th class="p-3">Node</th>
                                                    <th class="p-3">Status</th>
                                                    <th class="p-3">Specs</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-secondary-800/50 text-secondary-200">
                                                <tr v-for="server in unmanagedServers" :key="server.uniqueId"
                                                    class="hover:bg-secondary-800/30 cursor-pointer"
                                                    @click="toggleSelection(server.uniqueId)">
                                                    <td class="p-3" @click.stop>
                                                        <input type="checkbox"
                                                            :checked="selectedVmids.has(server.uniqueId)"
                                                            @change="toggleSelection(server.uniqueId)"
                                                            class="rounded border-secondary-600 bg-secondary-800 text-primary-500 focus:ring-primary-500/30" />
                                                    </td>
                                                    <td class="p-3 font-mono text-secondary-400">{{ server.vmid }}</td>
                                                    <td class="p-3 font-medium text-white">{{ server.name }}</td>
                                                    <td>
                                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-secondary-800 text-xs">
                                                            <ServerStackIcon class="w-3 h-3" /> {{ server.nodeName }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="uppercase text-xs font-bold"
                                                            :class="server.status === 'running' ? 'text-success-400' : 'text-danger-400'">
                                                            {{ server.status }}
                                                        </span>
                                                    </td>
                                                    <td class="p-3 text-xs text-secondary-400">
                                                        {{ server.cpu }}c / {{ server.memory_gb }}G / {{ server.disk_gb }}G
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="flex items-end gap-4 p-4 bg-secondary-800/20 rounded-lg border border-secondary-800/50">
                                        <div class="flex-1">
                                            <label class="block text-xs font-medium text-secondary-400 mb-1.5">Assign
                                                Owner for {{ selectedVmids.size }} selected servers</label>
                                            <div class="relative">
                                                <UserIcon
                                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-secondary-500" />
                                                <select v-model="selectedUserId"
                                                    class="w-full pl-9 pr-4 py-2 bg-secondary-900 border border-secondary-700 rounded-lg text-sm text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-colors">
                                                    <option value="">Select User...</option>
                                                    <option v-for="user in users" :key="user.id" :value="user.id">{{
                                                        user.name }} ({{ user.email }})</option>
                                                </select>
                                            </div>
                                        </div>
                                        <button @click="handleImport"
                                            :disabled="selectedVmids.size === 0 || !selectedUserId || importMutation.isPending.value"
                                            class="btn-primary h-[38px] px-6">
                                            <LoadingSpinner v-if="importMutation.isPending.value" size="sm"
                                                class="mr-2" />
                                            {{ importMutation.isPending.value ? 'Importing...' : 'Import Selected' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
