<script setup lang="ts">
import { computed, watch } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { dashboardApi } from '@/api';
import DashboardStatCard from '@/components/widgets/DashboardStatCard.vue';
import ServerActivity from '@/components/widgets/ServerActivity.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import { 
    CpuChipIcon, 
    ServerStackIcon, 
    MapPinIcon,
    ComputerDesktopIcon 
} from '@heroicons/vue/24/outline';

const { data, isLoading, isError, error } = useQuery({
    queryKey: ['admin', 'dashboard'],
    queryFn: () => dashboardApi.getStats(),
    refetchInterval: 30000, // Refresh every 30s
});

watch(data, (newVal: any) => {
    console.log('Dashboard Data:', newVal);
});

watch(isLoading, (newVal: boolean) => {
    console.log('Dashboard Loading:', newVal);
});

watch(isError, (newVal: boolean) => {
    if (newVal) console.error('Dashboard Error:', error.value);
});

const serverDetails = computed(() => {
    if (!data.value || !data.value.stats) return [];
    const s = data.value.stats.servers;
    return [
        { label: 'Running', value: s.active, color: 'text-success-400' },
        { label: 'Stopped', value: s.stopped, color: 'text-danger-400' },
        { label: 'Suspended', value: s.suspended, color: 'text-warning-400' },
    ];
});

const nodeDetails = computed(() => {
    if (!data.value) return [];
    const n = data.value.stats.nodes;
    return [
        { label: 'Online', value: n.online, color: 'text-success-400' },
        { label: 'Offline', value: n.offline, color: 'text-danger-400' },
    ];
});

const ipDetails = computed(() => {
    if (!data.value) return [];
    const i = data.value.stats.ips;
    return [
        { label: 'Available', value: i.available, color: 'text-warning-400' }, // Warning color to match "Available" concept or just use info
        { label: 'Assigned', value: i.assigned, color: 'text-secondary-400' },
    ];
});

const statusColor = (status: string) => {
    switch (status) {
        case 'running': return 'badge-success';
        case 'stopped': return 'badge-danger';
        case 'installing': return 'badge-warning';
        case 'suspended': return 'badge-warning'; 
        default: return 'badge-secondary';
    }
};
</script>

<template>
    <div class="p-6 space-y-6 animate-fade-in">
        <!-- Header -->
        <h1 class="text-2xl font-bold text-white">Dashboard</h1>

        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center py-20">
            <LoadingSpinner text="Loading dashboard..." />
        </div>

        <template v-else-if="data && data.stats">
            <!-- Top Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <DashboardStatCard
                    title="Servers"
                    subtitle="Total Servers"
                    :value="data.stats.servers.total"
                    :max="1000" 
                    color="success"
                    :icon="ServerStackIcon"
                    :details="serverDetails"
                />
                
                <DashboardStatCard
                    title="Nodes"
                    subtitle="Compute Nodes"
                    :value="data.stats.nodes.total"
                    :max="data.stats.nodes.total || 1"
                    color="primary"
                    :icon="CpuChipIcon"
                    :details="nodeDetails"
                />

                <DashboardStatCard
                    title="IPv4"
                    subtitle="Total IPs"
                    :value="data.stats.ips.total"
                    :max="data.stats.ips.total || 4096"
                    color="warning"
                    :icon="MapPinIcon"
                    :details="ipDetails"
                />
            </div>

            <!-- Middle Row: Activity Log (Full Width) -->
            <div class="h-96">
                <ServerActivity :activities="data.recent_activities" />
            </div>

            <!-- Bottom Row: Recent Servers -->
            <div class="card bg-secondary-900/50 border border-secondary-800 rounded-xl overflow-hidden">
                <div class="p-4 border-b border-secondary-800 flex items-center justify-between">
                    <h3 class="text-white font-medium">Recent Servers</h3>
                    <RouterLink to="/admin/servers" class="text-xs text-primary-400 hover:text-primary-300">View All</RouterLink>
                </div>
                <div class="overflow-x-auto">
                    <table class="table w-full text-left">
                        <thead>
                            <tr class="text-xs uppercase text-secondary-500 border-b border-secondary-800 bg-secondary-900/30">
                                <th class="p-3">Status</th>
                                <th class="p-3">VMID</th>
                                <th class="p-3">Server Name</th>
                                <th class="p-3">User</th>
                                <th class="p-3">Node</th>
                                <th class="p-3">IP Address</th>
                                <th class="p-3">vCPU</th>
                                <th class="p-3">RAM</th>
                                <th class="p-3">Disk</th>
                                <th class="p-3">Created</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-secondary-800">
                            <tr v-for="server in data.recent_servers" :key="server.id" class="hover:bg-white/5 transition-colors text-sm">
                                <td class="p-3">
                                    <span :class="statusColor(server.status)">{{ server.status }}</span>
                                </td>
                                <td class="p-3 font-mono text-secondary-400">{{ server.vmid }}</td>
                                <td class="p-3 font-medium text-white flex items-center gap-2">
                                    <!-- OS Icon placeholder -->
                                    <span class="w-4 h-4 rounded bg-blue-500/20 text-blue-400 flex items-center justify-center text-[10px] font-bold">L</span>
                                    {{ server.name }}
                                </td>
                                <td class="p-3 text-secondary-300">{{ server.user }}</td>
                                <td class="p-3 text-secondary-300 flex items-center gap-1">
                                    <img :src="`https://flagcdn.com/24x18/${server.location_code}.png`" class="w-4 h-3 rounded-sm opacity-75" alt="Flag" @error="($event.target as HTMLImageElement).style.display = 'none'" />
                                    {{ server.node }}
                                </td>
                                <td class="p-3 font-mono text-secondary-300">{{ server.ip }}</td>
                                <td class="p-3 text-secondary-300">{{ server.cpu }}</td>
                                <td class="p-3 text-secondary-300">{{ server.memory }}</td>
                                <td class="p-3 text-secondary-300">{{ server.disk }}</td>
                                <td class="p-3 text-secondary-500 text-xs">{{ server.created_at }}</td>
                                <td class="p-3 text-right">
                                    <RouterLink :to="`/admin/servers/${server.id}`" class="btn-secondary btn-sm">
                                        <ComputerDesktopIcon class="w-4 h-4" />
                                    </RouterLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!data.recent_servers.length" class="p-8 text-center text-secondary-500">
                        No servers found.
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
