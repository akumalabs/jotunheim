<script setup lang="ts">
import { computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { clientServerApi } from '@/api';
import {
    ServerStackIcon,
    PlayIcon,
    StopIcon,
    ArrowPathIcon,
    ComputerDesktopIcon,
} from '@heroicons/vue/24/outline';

const queryClient = useQueryClient();

// Fetch user's servers
const { data: servers, isLoading } = useQuery({
    queryKey: ['client', 'servers'],
    queryFn: () => clientServerApi.list(),
});

// Power mutation
const powerMutation = useMutation({
    mutationFn: ({ uuid, action }: { uuid: string; action: 'start' | 'stop' | 'restart' }) =>
        clientServerApi.power(uuid, action),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['client', 'servers'] });
    },
});

const handlePower = (uuid: string, action: 'start' | 'stop' | 'restart') => {
    powerMutation.mutate({ uuid, action });
};

const statusColor = (status: string) => {
    switch (status) {
        case 'running': return 'badge-success';
        case 'stopped': return 'badge-danger';
        case 'installing': return 'badge-warning';
        default: return 'badge-secondary';
    }
};

const statusDot = (status: string) => {
    switch (status) {
        case 'running': return 'status-dot-running';
        case 'stopped': return 'status-dot-stopped';
        default: return 'status-dot-pending';
    }
};
</script>

<template>
    <div class="space-y-6 animate-fade-in">
        <div>
            <h1 class="text-2xl font-bold text-white">Your Servers</h1>
            <p class="text-secondary-400">Manage your virtual machines</p>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="i in 3" :key="i" class="card card-body animate-pulse">
                <div class="h-6 bg-secondary-800 rounded w-1/2 mb-4"></div>
                <div class="h-4 bg-secondary-800 rounded w-1/3"></div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="!servers?.length" class="card card-body text-center py-12">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-secondary-800 flex items-center justify-center">
                <ServerStackIcon class="w-8 h-8 text-secondary-500" />
            </div>
            <h3 class="text-lg font-medium text-white mb-2">No servers yet</h3>
            <p class="text-secondary-400">Your servers will appear here once provisioned.</p>
        </div>

        <!-- Server cards -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="server in servers" :key="server.id" class="card">
                <div class="card-body">
                    <!-- Header -->
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="font-semibold text-white">{{ server.name }}</h3>
                            <p class="text-sm text-secondary-500">{{ server.hostname || server.uuid.slice(0, 8) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span :class="statusDot(server.status)"></span>
                            <span :class="statusColor(server.status)">{{ server.status }}</span>
                        </div>
                    </div>

                    <!-- Resources -->
                    <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                        <div>
                            <span class="text-secondary-500">CPU</span>
                            <p class="text-white">{{ server.cpu }} vCPU</p>
                        </div>
                        <div>
                            <span class="text-secondary-500">Memory</span>
                            <p class="text-white">{{ server.memory_formatted }}</p>
                        </div>
                        <div>
                            <span class="text-secondary-500">Disk</span>
                            <p class="text-white">{{ server.disk_formatted }}</p>
                        </div>
                        <div>
                            <span class="text-secondary-500">Location</span>
                            <p class="text-white">{{ server.node?.location?.short_code || '-' }}</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-4 border-t border-secondary-800">
                        <button
                            v-if="server.status === 'stopped'"
                            @click="handlePower(server.uuid, 'start')"
                            :disabled="powerMutation.isPending.value"
                            class="btn-success btn-sm flex-1"
                        >
                            <PlayIcon class="w-4 h-4 mr-1" />
                            Start
                        </button>
                        <button
                            v-if="server.status === 'running'"
                            @click="handlePower(server.uuid, 'stop')"
                            :disabled="powerMutation.isPending.value"
                            class="btn-danger btn-sm flex-1"
                        >
                            <StopIcon class="w-4 h-4 mr-1" />
                            Stop
                        </button>
                        <button
                            v-if="server.status === 'running'"
                            @click="handlePower(server.uuid, 'restart')"
                            :disabled="powerMutation.isPending.value"
                            class="btn-secondary btn-sm"
                        >
                            <ArrowPathIcon class="w-4 h-4" />
                        </button>
                        <RouterLink
                            :to="`/servers/${server.uuid}`"
                            class="btn-primary btn-sm"
                        >
                            <ComputerDesktopIcon class="w-4 h-4 mr-1" />
                            Manage
                        </RouterLink>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
