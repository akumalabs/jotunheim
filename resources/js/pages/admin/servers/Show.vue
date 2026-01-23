<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { adminServerApi } from '@/api';
import {
    ServerStackIcon,
    SignalIcon,
    CpuChipIcon,
    Cog6ToothIcon,
    WrenchScrewdriverIcon,
    PlayIcon,
    StopIcon,
    ArrowPathIcon,
    ChevronLeftIcon,
    TrashIcon,
    ShieldCheckIcon,
} from '@heroicons/vue/24/outline';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import RebuildModal from '@/components/RebuildModal.vue';
import AssignIPModal from '@/components/AssignIPModal.vue';
import FirewallManager from '@/components/FirewallManager.vue';
import ResourceEditor from '@/components/ResourceEditor.vue';
import TimeRangeSelector from '@/components/TimeRangeSelector.vue';
import UsageGraph from '@/components/UsageGraph.vue';

import { useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const queryClient = useQueryClient();
const serverId = Number(route.params.id);

// Fetch Server Details
const { data: server, isLoading } = useQuery({
    queryKey: ['admin', 'servers', serverId],
    queryFn: () => adminServerApi.get(serverId),
    retry: 1,
});

// Fetch Real-time Stats
const { data: stats } = useQuery({
    queryKey: ['admin', 'servers', serverId, 'status'],
    queryFn: () => adminServerApi.status(serverId),
    refetchInterval: 3000,
    enabled: () => !!server.value && server.value.status === 'running',
});

// Fetch Install Progress
const { data: installProgress } = useQuery({
    queryKey: ['admin', 'servers', serverId, 'install-progress'],
    queryFn: () => adminServerApi.installProgress(serverId),
    refetchInterval: 2000,
    enabled: () => !!server.value && (server.value.is_installing || server.value.status === 'rebuilding' || server.value.status === 'installing'),
});

// Power Actions
const powerMutation = useMutation({
    mutationFn: (action: 'start' | 'stop' | 'restart') =>
        adminServerApi.power(serverId, action),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers', serverId] });
    },
});

const handlePower = (action: 'start' | 'stop' | 'restart') => {
    powerMutation.mutate(action);
};



// Rebuild Modal
const showRebuildModal = ref(false);

// Network IP Management
const showAssignIPModal = ref(false);
const selectedIPForRemoval = ref<number | null>(null);

const removeIPMutation = useMutation({
    mutationFn: (addressId: number) => adminServerApi.removeIP(serverId, addressId),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers', serverId] });
        selectedIPForRemoval.value = null;
    },
    onError: (error: any) => {
        alert(error.response?.data?.message || 'Failed to remove IP address');
    },
});

const updateNetworkMutation = useMutation({
    mutationFn: () => adminServerApi.updateNetwork(serverId),
    onSuccess: () => {
        alert('Network configuration updated successfully');
    },
    onError: (error: any) => {
        alert(error.response?.data?.message || 'Failed to update network configuration');
    },
});

const handleRemoveIP = () => {
    if (!selectedIPForRemoval.value) return;

    const address = server.value?.addresses?.find(addr => addr.id === selectedIPForRemoval.value);
    if (address?.is_primary) {
        alert('Cannot remove primary IP address');
        return;
    }

    if (confirm('Remove selected IP address?')) {
        removeIPMutation.mutate(selectedIPForRemoval.value);
    }
};

const handleUpdateNetwork = () => {
    if (confirm('Update network configuration on the server?')) {
        updateNetworkMutation.mutate();
    }
};

// Set Primary IP
const setPrimaryMutation = useMutation({
    mutationFn: (addressId: number) => adminServerApi.setPrimaryIP(serverId, addressId),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers', serverId] });
    },
    onError: (error: any) => {
        alert(error.response?.data?.message || 'Failed to set primary IP');
    },
});

const handleSetPrimary = (addressId: number) => {
    if (confirm('Set this IP as the primary IP address?')) {
        setPrimaryMutation.mutate(addressId);
    }
};

// Reset Password
const newPassword = ref('');

const resetPasswordMutation = useMutation({
    mutationFn: () => adminServerApi.resetPassword(serverId, { password: newPassword.value }),
    onSuccess: () => {
        alert('Password reset successfully! New password: ' + newPassword.value);
        newPassword.value = '';
    },
    onError: (error: any) => {
        alert(error.response?.data?.message || 'Failed to reset password');
    },
});

const handleResetPassword = () => {
    if (!newPassword.value || resetPasswordMutation.isPending.value) return;
    if (confirm('Reset password? Server must be rebooted for changes to take effect.')) {
        resetPasswordMutation.mutate();
    }
};

const isResettingPassword = computed(() => resetPasswordMutation.isPending.value);

const deleteMutation = useMutation({
    mutationFn: (purge: boolean) => adminServerApi.delete(serverId, purge),
    onSuccess: () => {
        router.push('/admin/servers');
    },
});

const isDeleting = computed(() => deleteMutation.isPending.value);

// Delete Action
const deleteMode = ref<'full' | 'local'>('full');

// ...

const handleDelete = () => {
    const isFullDelete = deleteMode.value === 'full';
    const message = isFullDelete
        ? 'Are you sure you want to PERMANENTLY DELETE this server and destory the VM? This cannot be undone.'
        : 'Are you sure you want to remove this server from the panel? The VM will remain on the node.';

    if (confirm(message)) {
        deleteMutation.mutate(isFullDelete);
    }
};

// Open Console (for future implementation)
// const openConsole = () => {
//     window.open(`/admin/servers/${serverId}/console`, '_blank');
// };

// Tabs
const tabs = [
    { id: 'overview', name: 'Overview', icon: ServerStackIcon },
    { id: 'network', name: 'Network', icon: SignalIcon },
    { id: 'firewall', name: 'Firewall', icon: ShieldCheckIcon },
    { id: 'graphs', name: 'Statistics', icon: CpuChipIcon },
    { id: 'hardware', name: 'Hardware', icon: WrenchScrewdriverIcon },
    { id: 'settings', name: 'Settings', icon: Cog6ToothIcon },
];
const activeTab = ref('overview');

// Usage Graphs
// Map timeframe labels to Proxmox API values
const timeframeMap: Record<string, string> = {
    '30m': 'hour',
    '1h': 'hour',
    '12h': 'day',
    '1d': 'day',
    '1wk': 'week',
};

const selectedTimeframe = ref('30m'); // Start with 30m view
const { data: graphData } = useQuery({
    queryKey: computed(() => ['server-usage', serverId, selectedTimeframe.value]),
    queryFn: () => {
        const apiValue = timeframeMap[selectedTimeframe.value] || 'hour';
        return adminServerApi.getUsageData(serverId, apiValue);
    },
    refetchInterval: () => {
        const apiValue = timeframeMap[selectedTimeframe.value] || 'hour';
        return apiValue === 'hour' ? 30000 : 60000;
    },
    enabled: computed(() => activeTab.value === 'graphs'),
});

const statusColor = (status: string) => {
    switch (status) {
        case 'running': return 'text-success-400 bg-success-400/10 border-success-400/20';
        case 'stopped': return 'text-danger-400 bg-danger-400/10 border-danger-400/20';
        case 'installing': return 'text-warning-400 bg-warning-400/10 border-warning-400/20';
        default: return 'text-secondary-400 bg-secondary-400/10 border-secondary-400/20';
    }
};
</script>

<template>
    <div class="space-y-6 animate-fade-in pb-12">
        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center min-h-[50vh]">
            <LoadingSpinner text="Loading server details..." />
        </div>

        <template v-else-if="server">
            <!-- Breadcrumbs / Back -->
            <div class="px-6 pt-6">
                <RouterLink to="/admin/servers"
                    class="inline-flex items-center text-secondary-400 hover:text-white transition-colors">
                    <ChevronLeftIcon class="w-4 h-4 mr-1" />
                    Back to Servers
                </RouterLink>
            </div>

            <!-- Header -->
            <div class="px-6 flex flex-col md:flex-row gap-6 justify-between items-start md:items-center">
                    <div class="w-full"
                    v-if="server.is_installing || server.status === 'rebuilding' || server.status === 'installing'">
                    <!-- Installation Progress -->
                    <div class="bg-secondary-800 rounded-lg p-4 border border-secondary-700 mb-6 animate-pulse-slow">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-white flex items-center gap-2">
                                <ArrowPathIcon class="w-4 h-4 animate-spin" />
                                {{ installProgress?.stepLabel || 'Provisioning server...' }}
                            </span>
                            <span class="text-xs text-secondary-400 font-mono">{{ installProgress?.hasProgress ?
                                Math.round(installProgress.cloneProgress || installProgress.progress || 0) : installProgress?.progress || 0 }}%</span>
                        </div>
                        <div class="w-full bg-secondary-900 h-2 rounded-full overflow-hidden">
                            <div class="bg-primary-500 h-full transition-all duration-1000 ease-out"
                                :style="{ width: `${installProgress?.progress || 0}%` }"></div>
                        </div>
                        <p class="text-xs text-secondary-500 mt-2">
                            Please wait while server is being provisioned.
                        </p>
                    </div>
 
                    <div class="flex items-center gap-3 mb-1">
                        <h1 class="text-3xl font-bold text-white">{{ server.name }}</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border uppercase tracking-wider"
                            :class="statusColor(server.status)">
                            {{ server.status }}
                        </span>
                    </div>
                </div>

                <!-- Power Controls -->
                <div class="flex items-center bg-secondary-900 border border-secondary-800 rounded-lg p-1">
                    <template v-if="server.status === 'running'">
                        <button @click="handlePower('restart')" :disabled="powerMutation.isPending.value"
                            class="px-4 py-2 flex items-center gap-2 text-secondary-300 hover:text-white hover:bg-secondary-800 rounded transition-colors disabled:opacity-50">
                            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': powerMutation.isPending.value }" />
                            Restart
                        </button>
                        <div class="w-px h-6 bg-secondary-800"></div>
                        <button @click="handlePower('stop')" :disabled="powerMutation.isPending.value"
                            class="px-4 py-2 flex items-center gap-2 text-danger-400 hover:text-danger-300 hover:bg-danger-500/10 rounded transition-colors disabled:opacity-50">
                            <StopIcon class="w-4 h-4" />
                            Stop
                        </button>
                    </template>
                    <template v-else>
                        <button @click="handlePower('start')" :disabled="powerMutation.isPending.value"
                            class="px-4 py-2 flex items-center gap-2 text-success-400 hover:text-success-300 hover:bg-success-500/10 rounded transition-colors disabled:opacity-50">
                            <PlayIcon class="w-4 h-4" />
                            Start Server
                        </button>
                    </template>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="px-6 border-b border-secondary-800">
                <nav class="flex space-x-6">
                    <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                        class="pb-4 px-2 text-sm font-medium border-b-2 transition-colors flex items-center gap-2"
                        :class="activeTab === tab.id
                            ? 'border-primary-500 text-primary-400'
                            : 'border-transparent text-secondary-400 hover:text-secondary-200 hover:border-secondary-700'">
                        <component :is="tab.icon" class="w-4 h-4" />
                        {{ tab.name }}
                    </button>
                </nav>
            </div>

            <!-- Content Area -->
            <div class="px-6">
                <!-- Overview Tab -->
                <div v-show="activeTab === 'overview'" class="space-y-6 animate-fade-in">
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div class="card p-4 flex flex-col gap-1">
                            <span
                                class="text-secondary-400 text-xs uppercase tracking-wider font-semibold">Uptime</span>
                            <span class="text-xl font-mono text-white">
                                {{ stats ? Math.floor(stats.uptime / 3600) + 'h ' + Math.floor((stats.uptime % 3600) /
                                    60) + 'm' :
                                    '-' }}
                            </span>
                        </div>
                        <div class="card p-4 flex flex-col gap-1">
                            <span class="text-secondary-400 text-xs uppercase tracking-wider font-semibold">CPU
                                Usage</span>
                            <div class="flex items-end gap-2">
                                <span class="text-xl font-mono text-white">{{ stats ? (stats.cpu * 100).toFixed(1) : 0
                                    }}%</span>
                                <span class="text-xs text-secondary-500 mb-1">of {{ server.cpu }} vCore(s)</span>
                            </div>
                            <div class="w-full bg-secondary-800 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div class="bg-primary-500 h-full transition-all duration-500"
                                    :style="{ width: `${stats ? (stats.cpu * 100) : 0}%` }"></div>
                            </div>
                        </div>
                        <div class="card p-4 flex flex-col gap-1">
                            <span
                                class="text-secondary-400 text-xs uppercase tracking-wider font-semibold">Memory</span>
                            <div class="flex items-end gap-2">
                                <span class="text-xl font-mono text-white">{{ stats?.mem ? Math.round(stats.mem /
                                    1024 / 1024) :
                                    0 }} MB</span>
                                <span class="text-xs text-secondary-500 mb-1">/ {{ server.memory_formatted }}</span>
                            </div>
                            <div class="w-full bg-secondary-800 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div class="bg-primary-500 h-full transition-all duration-500"
                                    :style="{ width: `${stats?.mem && stats?.maxmem ? (stats.mem / stats.maxmem * 100) : 0}%` }">
                                </div>
                            </div>
                        </div>
                        <div class="card p-4 flex flex-col gap-1">
                            <span class="text-secondary-400 text-xs uppercase tracking-wider font-semibold">Disk</span>
                            <div class="flex items-end gap-2">
                                <span class="text-xl font-mono text-white">{{ stats?.disk ? Math.round((stats.disk as
                                    any) / 1024 /
                                    1024 / 1024) : 0 }} GB</span>
                                <span class="text-xs text-secondary-500 mb-1">/ {{ server.disk_formatted }}</span>
                            </div>
                            <div class="w-full bg-secondary-800 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div class="bg-primary-500 h-full transition-all duration-500"
                                    :style="{ width: `${stats?.disk && stats.maxdisk ? ((stats.disk as any) / (stats.maxdisk as any) * 100) : 0}%` }">
                                </div>
                            </div>
                        </div>
                        <div class="card p-4 flex flex-col gap-1">
                            <span
                                class="text-secondary-400 text-xs uppercase tracking-wider font-semibold">Bandwidth</span>
                            <div class="flex items-end gap-2">
                                <span class="text-xl font-mono text-white">{{ Math.round((server.bandwidth_usage || 0) /
                                    1024 / 1024
                                    / 1024) }} GB</span>
                                <span class="text-xs text-secondary-500 mb-1">/ {{ server.bandwidth_limit ?
                                    Math.round(server.bandwidth_limit / 1024 / 1024 / 1024 / 1024) + ' TB' : 'Unlimited'
                                    }}</span>
                            </div>
                            <div class="w-full bg-secondary-800 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div class="bg-primary-500 h-full transition-all duration-500"
                                    :style="{ width: server.bandwidth_limit ? `${((server.bandwidth_usage || 0) / server.bandwidth_limit * 100)}%` : '0%' }">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Details Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Left Col -->
                        <div class="md:col-span-2 card">
                            <div class="card-header">
                                <h3 class="text-lg font-semibold text-white">System Information</h3>
                            </div>
                            <div class="card-body grid grid-cols-2 gap-y-6">
                                <div>
                                    <h4 class="text-xs font-semibold text-secondary-500 uppercase">VMID</h4>
                                    <div class="mt-1 font-mono text-secondary-200">{{ server.vmid }}</div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-secondary-500 uppercase">Guest Agent</h4>
                                    <div class="mt-1 flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full"
                                            :class="stats ? 'bg-success-500' : 'bg-danger-500'"></div>
                                        <span class="text-secondary-200">{{ stats ? 'Connected' : 'Not Connected'
                                            }}</span>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-secondary-500 uppercase">Owner</h4>
                                    <div class="mt-1 flex items-center gap-2">
                                        <div
                                            class="w-6 h-6 rounded-full bg-secondary-700 flex items-center justify-center text-xs text-secondary-300 font-bold">
                                            {{ server.user?.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <span class="text-secondary-200">{{ server.user?.name }}</span>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-secondary-500 uppercase">Location</h4>
                                    <div class="mt-1 text-secondary-200">{{ server.node?.location?.name || 'N/A' }}
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-secondary-500 uppercase">Hypervisor Node</h4>
                                    <div class="mt-1 text-secondary-200">{{ server.node?.name }} <span
                                            class="text-secondary-500 text-xs">({{ server.node?.location?.short_code ||
                                                'Local'
                                            }})</span></div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-secondary-500 uppercase">Created</h4>
                                    <div class="mt-1 text-secondary-200">{{ new
                                        Date(server.created_at).toLocaleDateString() }}
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-secondary-500 uppercase">Primary IP</h4>
                                    <div class="mt-1 font-mono text-secondary-200">{{ server.addresses?.[0] ?
                                        `${server.addresses[0].address}/${server.addresses[0].cidr}` : 'No IP Assigned'
                                        }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Network Tab -->
                <div v-show="activeTab === 'network'" class="animate-fade-in">
                    <div class="glass-card border border-secondary-700/50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Network Configuration</h3>

                        <!-- IP Addresses -->
                        <div class="space-y-3">
                            <div class="flex justify-between items-center mb-4">
                                <label class="label">Assigned IP Addresses</label>
                                <div class="flex gap-2">
                                    <button @click="handleRemoveIP"
                                        :disabled="!selectedIPForRemoval || removeIPMutation.isPending.value"
                                        class="px-3 py-1.5 bg-red-500/20 hover:bg-red-500/30 text-red-300 rounded-lg transition-colors flex items-center gap-1.5 text-sm font-medium disabled:opacity-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Remove Selected
                                    </button>
                                    <button @click="showAssignIPModal = true"
                                        class="px-3 py-1.5 bg-primary-500 hover:bg-primary-600 text-white rounded-lg transition-colors flex items-center gap-1.5 text-sm font-medium">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                        Assign IP
                                    </button>
                                    <button @click="handleUpdateNetwork"
                                        :disabled="updateNetworkMutation.isPending.value"
                                        class="px-3 py-1.5 bg-secondary-700 hover:bg-secondary-600 text-white rounded-lg transition-colors flex items-center gap-1.5 text-sm font-medium disabled:opacity-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        {{ updateNetworkMutation.isPending.value ? 'Updating...' : 'Update Server' }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="server.addresses && server.addresses.length > 0" class="space-y-2">
                                <label v-for="address in server.addresses" :key="address.id"
                                    class="flex items-center gap-3 p-3 bg-secondary-800/50 rounded-lg cursor-pointer hover:bg-secondary-800 transition-colors"
                                    :class="{ 'opacity-50': address.is_primary }">
                                    <!-- Radio button for selection -->
                                    <input type="radio" name="ip-selection" :value="address.id"
                                        v-model="selectedIPForRemoval" :disabled="address.is_primary"
                                        class="w-4 h-4 text-primary-500 focus:ring-primary-500">

                                    <!-- IP Icon -->
                                    <div :class="address.type === 'ipv4' ? 'text-blue-400' : 'text-purple-400'">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                        </svg>
                                    </div>

                                    <!-- IP Details -->
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-white font-mono">
                                            {{ address.address }}/{{ address.cidr }}
                                        </div>
                                        <div class="text-xs text-secondary-500">
                                            Gateway: {{ address.gateway || 'N/A' }}
                                        </div>
                                    </div>

                                    <!-- Badges & Actions -->
                                    <div class="flex items-center gap-2">
                                        <span :class="[
                                            'px-2 py-1 text-xs rounded font-medium',
                                            address.type === 'ipv4' ? 'bg-blue-500/20 text-blue-300' : 'bg-purple-500/20 text-purple-300'
                                        ]">
                                            {{ address.type.toUpperCase() }}
                                        </span>
                                        <span v-if="address.is_primary"
                                            class="px-2 py-1 text-xs rounded font-medium bg-green-500/20 text-green-300">
                                            Primary
                                        </span>
                                        <button v-else @click.stop="handleSetPrimary(address.id)"
                                            :disabled="setPrimaryMutation.isPending.value"
                                            class="px-3 py-1.5 text-xs font-medium rounded bg-blue-500/20 text-blue-300 hover:bg-blue-500/30 transition-colors disabled:opacity-50">
                                            Set as Primary
                                        </button>
                                    </div>
                                </label>
                            </div>
                            <div v-else
                                class="text-sm text-secondary-500 p-4 bg-secondary-800/30 rounded-lg text-center">
                                No IP addresses assigned
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Firewall Tab -->
                <div v-show="activeTab === 'firewall'" class="animate-fade-in">
                    <FirewallManager :server-id="serverId" :server="server" />
                </div>

                <!-- Graphs Tab -->
                <div v-show="activeTab === 'graphs'" class="animate-fade-in space-y-6">
                    <!-- Time Range Selector -->
                    <div class="flex justify-end">
                        <TimeRangeSelector v-model="selectedTimeframe" />
                    </div>

                    <!-- Usage Graphs Grid -->
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Network I/O -->
                        <UsageGraph title="Network Traffic" :data="graphData || []" :value-key="['netin', 'netout']"
                            :label="['Incoming', 'Outgoing']" :color="['#3b82f6', '#8b5cf6']" :is-loading="!graphData"
                            unit="mbps" />

                        <!-- CPU Usage -->
                        <UsageGraph title="CPU Usage" :data="graphData || []" value-key="cpu" label="CPU"
                            color="#f59e0b" :is-loading="!graphData" unit="percent" />

                        <!-- Memory Usage -->
                        <UsageGraph title="Memory Usage" :data="graphData || []" value-key="mem" label="Memory"
                            color="#10b981" :is-loading="!graphData" unit="percent"
                            :max-value="graphData?.[0]?.maxmem" />

                        <!-- Disk I/O -->
                        <UsageGraph title="Disk I/O" :data="graphData || []" :value-key="['diskread', 'diskwrite']"
                            :label="['Read', 'Write']" :color="['#ec4899', '#f97316']" :is-loading="!graphData"
                            unit="mbs" />
                    </div>
                </div>

                <!-- Hardware Tab -->
                <div v-show="activeTab === 'hardware'" class="animate-fade-in">
                    <ResourceEditor :server="server!" :is-admin="true"
                        @success="() => queryClient.invalidateQueries({ queryKey: ['admin', 'servers', serverId] })" />
                </div>

                <!-- Settings Tab -->
                <div v-show="activeTab === 'settings'" class="animate-fade-in space-y-6">
                    <!-- Reset Password -->
                    <div class="glass-card border border-secondary-700/50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-white mb-2">Reset Password</h3>
                        <p class="text-sm text-secondary-400 mb-4">Change the server's password. Requires reboot to
                            take
                            effect.</p>

                        <div class="max-w-md space-y-3">
                            <div>
                                <label class="label mb-2">New Password</label>
                                <input v-model="newPassword" type="password" class="input w-full"
                                    placeholder="Enter new password" />
                                <p class="text-xs text-secondary-500 mt-1.5">Min 8 chars with uppercase, lowercase,
                                    number, and
                                    symbol</p>
                            </div>
                            <button @click="handleResetPassword" :disabled="!newPassword || !!isResettingPassword"
                                class="btn btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                                <span v-if="isResettingPassword">Resetting...</span>
                                <span v-else>Reset Password</span>
                            </button>
                        </div>
                    </div>

                    <!-- Rebuild Server -->
                    <div class="glass-card border border-secondary-700/50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-white mb-2">Rebuild Server</h3>
                        <p class="text-sm text-secondary-400 mb-4">Reinstall the server with a new operating system
                            template. All
                            data will be lost.</p>
                        <button @click="showRebuildModal = true" class="btn btn-secondary">
                            <ArrowPathIcon class="w-4 h-4 mr-2" />
                            Rebuild Server
                        </button>
                    </div>

                    <!-- Delete Server (Admin Only) -->
                    <div v-if="true" class="card border border-danger-500/20">
                        <div class="card-header border-b border-danger-500/20 bg-danger-500/5">
                            <div class="flex items-center gap-2 text-danger-400">
                                <TrashIcon class="w-5 h-5" />
                                <h3 class="font-semibold">Delete Server</h3>
                            </div>
                        </div>
                        <div class="card-body flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-white">Permanently Delete</h4>
                                <p class="text-xs text-secondary-400 mt-1">Delete this server and all its data. This
                                    action cannot
                                    be undone.</p>
                                <div class="mt-4 space-y-3">
                                    <div class="flex items-start">
                                        <input id="delete-full" type="radio" v-model="deleteMode" value="full"
                                            class="mt-1 w-4 h-4 text-danger-500 border-secondary-700 bg-secondary-900 focus:ring-danger-500/50">
                                        <label for="delete-full" class="ml-3 cursor-pointer">
                                            <span class="block text-sm font-medium text-white">Full Delete (Panel +
                                                PVE)</span>
                                            <span class="block text-xs text-secondary-400">Delete from panel AND destroy
                                                virtual
                                                machine on node</span>
                                        </label>
                                    </div>
                                    <div class="flex items-start">
                                        <input id="delete-local" type="radio" v-model="deleteMode" value="local"
                                            class="mt-1 w-4 h-4 text-danger-500 border-secondary-700 bg-secondary-900 focus:ring-danger-500/50">
                                        <label for="delete-local" class="ml-3 cursor-pointer">
                                            <span class="block text-sm font-medium text-white">Soft Delete (Panel
                                                Only)</span>
                                            <span class="block text-xs text-secondary-400">Remove from panel but KEEP
                                                virtual
                                                machine running</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <button @click="handleDelete" :disabled="isDeleting"
                                class="px-4 py-2 bg-danger-500/10 hover:bg-danger-500/20 text-danger-400 border border-danger-500/20 rounded transition-colors text-sm font-medium disabled:opacity-50 flex items-center gap-2">
                                <TrashIcon class="w-4 h-4" />
                                <span v-if="isDeleting">Deleting...</span>
                                <span v-else>Delete Server</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Not Found -->
        <div v-else class="text-center py-20">
            <h3 class="text-lg font-medium text-white">Server not found</h3>
            <RouterLink to="/admin/servers" class="btn-secondary mt-4">Go Back</RouterLink>
        </div>

        <!-- Modals -->
        <RebuildModal v-if="server" :show="showRebuildModal" :server="server" @close="showRebuildModal = false"
            @success="() => {
                queryClient.invalidateQueries({ queryKey: ['admin', 'servers', serverId] });
                queryClient.invalidateQueries({ queryKey: ['server-usage', serverId] });
            }" />
        <AssignIPModal v-if="server" :open="showAssignIPModal" :server-id="serverId" @close="showAssignIPModal = false"
            @success="() => queryClient.invalidateQueries({ queryKey: ['admin', 'servers', serverId] })" />
    </div>
</template>
