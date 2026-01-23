<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { clientServerApi } from '@/api';
import VncConsole from '@/components/VncConsole.vue';
import ServerSettingsModal from '@/components/ServerSettingsModal.vue';
import SnapshotsPanel from '@/components/SnapshotsPanel.vue';
import IsoModal from '@/components/IsoModal.vue';
import {
    ArrowLeftIcon,
    ServerStackIcon,
    SignalIcon,
    CpuChipIcon,
    Cog6ToothIcon,
    ComputerDesktopIcon,
    PlayIcon,
    StopIcon,
    ArrowPathIcon,
    CameraIcon,
    CircleStackIcon,
} from '@heroicons/vue/24/outline';
import LoadingSpinner from '@/components/LoadingSpinner.vue';

const route = useRoute();
const queryClient = useQueryClient();
const uuid = computed(() => route.params.uuid as string);

// State
const showConsole = ref(false);
const showSettings = ref(false);
const showIso = ref(false);
const activeTab = ref('overview');

const tabs = [
    { id: 'overview', name: 'Overview', icon: ServerStackIcon },
    { id: 'resources', name: 'Resources', icon: CpuChipIcon },
    { id: 'network', name: 'Network', icon: SignalIcon },
    { id: 'snapshots', name: 'Snapshots', icon: CameraIcon },
    { id: 'settings', name: 'Settings', icon: Cog6ToothIcon },
];

// Fetch
const { data: server, isLoading } = useQuery({
    queryKey: ['client', 'server', uuid],
    queryFn: () => clientServerApi.get(uuid.value),
});

// Status Poll
const { data: status, refetch: refetchStatus } = useQuery({
    queryKey: ['client', 'server', uuid, 'status'],
    queryFn: () => clientServerApi.status(uuid.value),
    refetchInterval: 3000,
    enabled: computed(() => !!server.value),
});

// Power
const powerMutation = useMutation({
    mutationFn: (action: 'start' | 'stop' | 'restart' | 'shutdown' | 'kill') =>
        clientServerApi.power(uuid.value, action),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['client', 'server', uuid.value] });
        refetchStatus();
    },
});

const handlePower = (action: 'start' | 'stop' | 'restart' | 'shutdown' | 'kill') => {
    powerMutation.mutate(action);
};

// Utilities
const currentStatus = computed(() => status.value?.status || server.value?.status || 'unknown');
const isRunning = computed(() => currentStatus.value === 'running');

const formatBytes = (bytes: number): string => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatUptime = (seconds: number): string => {
    if (!seconds) return '0m';
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${mins}m`;
    return `${mins}m`;
};

const statusColor = (status: string) => {
    switch (status) {
        case 'running': return 'text-success-400 bg-success-400/10 border-success-400/20';
        case 'stopped': return 'text-danger-400 bg-danger-400/10 border-danger-400/20';
        case 'installing': return 'text-warning-400 bg-warning-400/10 border-warning-400/20';
        default: return 'text-secondary-400 bg-secondary-400/10 border-secondary-400/20';
    }
};

const cpuUsage = computed(() => status.value?.cpu ? (status.value.cpu * 100).toFixed(1) : '0');
const memUsage = computed(() => status.value?.memory ? (status.value.memory.used / status.value.memory.total * 100).toFixed(1) : '0');
</script>

<template>
    <div class="space-y-6 animate-fade-in pb-12">
        <!-- Loading -->
        <div v-if="isLoading" class="flex items-center justify-center min-h-[50vh]">
            <LoadingSpinner text="Loading server..." />
        </div>

        <template v-else-if="server">
            <!-- Breadcrumbs -->
            <div class="px-6 pt-6">
                <RouterLink to="/servers" class="inline-flex items-center text-secondary-400 hover:text-white transition-colors">
                    <ArrowLeftIcon class="w-4 h-4 mr-1" />
                    Back to Servers
                </RouterLink>
            </div>

            <!-- Header -->
            <div class="px-6 flex flex-col md:flex-row gap-6 justify-between items-start md:items-center">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <h1 class="text-3xl font-bold text-white">{{ server.name }}</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border uppercase tracking-wider" :class="statusColor(currentStatus)">
                            {{ currentStatus }}
                        </span>
                    </div>
                    <div class="flex items-center gap-4 text-sm text-secondary-400 font-mono">
                        <span>{{ server.hostname || server.uuid }}</span>
                        <span v-if="server.addresses?.[0]">• {{ server.addresses[0].address }}</span>
                    </div>
                </div>

                <!-- Power Controls -->
                <div class="flex items-center bg-secondary-900 border border-secondary-800 rounded-lg p-1">
                     <button @click="showConsole = true" :disabled="!isRunning" class="px-4 py-2 flex items-center gap-2 text-secondary-300 hover:text-white hover:bg-secondary-800 rounded transition-colors disabled:opacity-50 border-r border-secondary-800 mr-1">
                        <ComputerDesktopIcon class="w-4 h-4" />
                        Console
                    </button>
                    
                    <template v-if="isRunning">
                         <button @click="handlePower('restart')" :disabled="powerMutation.isPending.value" class="px-4 py-2 flex items-center gap-2 text-secondary-300 hover:text-white hover:bg-secondary-800 rounded transition-colors disabled:opacity-50">
                            <ArrowPathIcon class="w-4 h-4" :class="{'animate-spin': powerMutation.isPending.value}" />
                            Restart
                        </button>
                        <div class="w-px h-6 bg-secondary-800 mx-1"></div>
                        <button @click="handlePower('stop')" :disabled="powerMutation.isPending.value" class="px-4 py-2 flex items-center gap-2 text-danger-400 hover:text-danger-300 hover:bg-danger-500/10 rounded transition-colors disabled:opacity-50">
                            <StopIcon class="w-4 h-4" />
                            Stop
                        </button>
                    </template>
                    <template v-else>
                        <button @click="handlePower('start')" :disabled="powerMutation.isPending.value" class="px-4 py-2 flex items-center gap-2 text-success-400 hover:text-success-300 hover:bg-success-500/10 rounded transition-colors disabled:opacity-50">
                            <PlayIcon class="w-4 h-4" />
                            Start
                        </button>
                    </template>
                </div>
            </div>

            <!-- Tabs -->
            <div class="px-6 border-b border-secondary-800">
                <nav class="flex space-x-6 overflow-x-auto">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        class="pb-4 px-2 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap"
                        :class="activeTab === tab.id 
                            ? 'border-primary-500 text-primary-400' 
                            : 'border-transparent text-secondary-400 hover:text-secondary-200 hover:border-secondary-700'"
                    >
                        <component :is="tab.icon" class="w-4 h-4" />
                        {{ tab.name }}
                    </button>
                </nav>
            </div>

            <!-- Content -->
            <div class="px-6">
                <!-- Overview -->
                <div v-show="activeTab === 'overview'" class="space-y-6 animate-fade-in">
                    <!-- Metrics Row -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                         <!-- Uptime -->
                        <div class="card p-4 flex flex-col gap-1">
                            <span class="text-secondary-400 text-xs uppercase tracking-wider font-semibold">Uptime</span>
                            <span class="text-xl font-mono text-white">{{ isRunning ? formatUptime(status?.uptime || 0) : 'Offline' }}</span>
                        </div>
                        <!-- CPU -->
                        <div class="card p-4 flex flex-col gap-1">
                             <span class="text-secondary-400 text-xs uppercase tracking-wider font-semibold">CPU Load</span>
                             <div class="flex items-end gap-2">
                                <span class="text-xl font-mono text-white">{{ cpuUsage }}%</span>
                                <span class="text-xs text-secondary-500 mb-1">of {{ server.cpu }} vCore(s)</span>
                             </div>
                             <div class="w-full bg-secondary-800 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div class="bg-primary-500 h-full transition-all duration-500" :style="{ width: `${Number(cpuUsage)}%` }"></div>
                             </div>
                        </div>
                        <!-- RAM -->
                        <div class="card p-4 flex flex-col gap-1">
                             <span class="text-secondary-400 text-xs uppercase tracking-wider font-semibold">Memory</span>
                             <div class="flex items-end gap-2">
                                <span class="text-xl font-mono text-white">{{ status?.memory ? formatBytes(status.memory.used) : '0 B' }}</span>
                                <span class="text-xs text-secondary-500 mb-1">/ {{ server.memory_formatted }}</span>
                             </div>
                             <div class="w-full bg-secondary-800 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div class="bg-primary-500 h-full transition-all duration-500" :style="{ width: `${Number(memUsage)}%` }"></div>
                             </div>
                        </div>
                         <!-- Storage -->
                         <div class="card p-4 flex flex-col gap-1">
                             <span class="text-secondary-400 text-xs uppercase tracking-wider font-semibold">Storage</span>
                             <div class="flex items-end gap-2">
                                <span class="text-xl font-mono text-white">{{ server.disk_formatted }}</span>
                                <span class="text-xs text-secondary-500 mb-1">Allocated</span>
                             </div>
                        </div>
                    </div>

                    <!-- System & Network Info -->
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="text-lg font-semibold text-white">System Information</h3>
                            </div>
                            <div class="card-body grid grid-cols-2 gap-y-6">
                                <div>
                                    <h4 class="text-xs font-semibold text-secondary-500 uppercase">OS Template</h4>
                                    <div class="mt-1 text-secondary-200">Standard Linux</div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-secondary-500 uppercase">Virtualization</h4>
                                    <div class="mt-1 text-secondary-200">KVM (Proxmox)</div>
                                </div>
                                 <div>
                                    <h4 class="text-xs font-semibold text-secondary-500 uppercase">Primary IP</h4>
                                    <div class="mt-1 font-mono text-secondary-200 text-sm">{{ server.addresses?.[0]?.address || 'Unassigned' }}</div>
                                </div>
                                 <div class="col-span-2">
                                     <h4 class="text-xs font-semibold text-secondary-500 uppercase mb-2">Bandwidth Usage</h4>
                                     <div class="flex items-center justify-between text-sm text-secondary-300 mb-1">
                                         <span>{{ formatBytes(server.bandwidth_usage) }}</span>
                                         <span>{{ server.bandwidth_limit ? formatBytes(server.bandwidth_limit) : 'Unlimited' }}</span>
                                     </div>
                                      <div class="w-full bg-secondary-800 h-2 rounded-full overflow-hidden">
                                        <div class="bg-success-500 h-full transition-all duration-500" style="width: 2%"></div>
                                     </div>
                                </div>
                            </div>
                        </div>

                         <div class="card">
                            <div class="card-header">
                                <h3 class="text-lg font-semibold text-white">Quick Actions</h3>
                            </div>
                            <div class="card-body space-y-3">
                                <button @click="showIso = true" class="w-full btn-secondary justify-between group">
                                    <span class="flex items-center gap-2"><CircleStackIcon class="w-5 h-5 text-secondary-400 group-hover:text-white" /> Mount ISO Image</span>
                                    <span class="text-secondary-500 text-xs group-hover:text-secondary-300">Boot from CD/DVD</span>
                                </button>
                                 <button @click="showSettings = true" class="w-full btn-secondary justify-between group">
                                    <span class="flex items-center gap-2"><Cog6ToothIcon class="w-5 h-5 text-secondary-400 group-hover:text-white" /> Settings & Reinstall</span>
                                    <span class="text-secondary-500 text-xs group-hover:text-secondary-300">Manage VM</span>
                                </button>
                                <div class="pt-2 border-t border-secondary-800">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-secondary-400">Network Traffic (Current)</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 mt-2">
                                        <div class="bg-secondary-900/50 p-2 rounded border border-secondary-800 text-center">
                                            <div class="text-xs text-secondary-500">Inbound</div>
                                            <div class="font-mono text-white">{{ formatBytes(status?.network?.in || 0) }}</div>
                                        </div>
                                         <div class="bg-secondary-900/50 p-2 rounded border border-secondary-800 text-center">
                                            <div class="text-xs text-secondary-500">Outbound</div>
                                            <div class="font-mono text-white">{{ formatBytes(status?.network?.out || 0) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                     </div>
                </div>

                <!-- Resources Placeholder -->
                <div v-show="activeTab === 'resources'" class="animate-fade-in py-12 text-center">
                    <CpuChipIcon class="w-12 h-12 text-secondary-700 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-secondary-300">Detailed Graphs Coming Soon</h3>
                    <p class="text-secondary-500">Historical CPU, RAM, and Network usage data.</p>
                </div>

                <!-- Network -->
                 <div v-show="activeTab === 'network'" class="animate-fade-in space-y-6">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="text-lg font-semibold text-white">IP Addresses</h2>
                        </div>
                        <div class="card-body">
                             <div v-if="server.addresses?.length" class="overflow-x-auto">
                                <table class="table w-full">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Address</th>
                                            <th>Gateway</th>
                                            <th>Primary</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="addr in server.addresses" :key="addr.id">
                                            <td class="uppercase">{{ addr.type }}</td>
                                            <td class="font-mono text-white">{{ addr.address }}/{{ addr.cidr }}</td>
                                            <td class="font-mono text-secondary-300">{{ addr.gateway }}</td>
                                            <td>
                                                <span v-if="addr.is_primary" class="badge-success">Primary</span>
                                                <span v-else class="text-secondary-500 text-xs">-</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                             <p v-else class="text-secondary-500 text-center py-4">No IP addresses assigned</p>
                        </div>
                    </div>
                 </div>

                <!-- Snapshots -->
                <div v-show="activeTab === 'snapshots'" class="animate-fade-in">
                    <SnapshotsPanel :server-uuid="uuid" />
                </div>

                <!-- Settings -->
                 <div v-show="activeTab === 'settings'" class="animate-fade-in">
                     <div class="card card-body text-center py-12">
                         <Cog6ToothIcon class="w-12 h-12 text-secondary-700 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-secondary-300">Server Settings</h3>
                        <p class="text-secondary-500 mb-6">Use the modal for advanced actions.</p>
                        <button @click="showSettings = true" class="btn-primary">Open Settings</button>
                     </div>
                 </div>

            </div>
        </template>
        
         <!-- VNC Console Modal -->
        <VncConsole 
            v-if="server"
            :server-uuid="uuid" 
            :show="showConsole"
            @close="showConsole = false"
        />
        
        <!-- Settings Modal -->
        <ServerSettingsModal
            :server-uuid="uuid"
            :show="showSettings"
            @close="showSettings = false"
        />
        
        <!-- ISO Modal -->
        <IsoModal
            :server-uuid="uuid"
            :show="showIso"
            @close="showIso = false"
        />
    </div>
</template>
