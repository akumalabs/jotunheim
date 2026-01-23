<script setup lang="ts">
import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { useAuthStore } from '@/stores/auth';
import { clientServerApi } from '@/api';
import { ServerStackIcon } from '@heroicons/vue/24/outline';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

const authStore = useAuthStore();

// Fetch user's servers
const { data: servers, isLoading } = useQuery({
    queryKey: ['client', 'servers'],
    queryFn: () => clientServerApi.list(),
});

// Stats
const stats = computed(() => ({
    total: servers.value?.length ?? 0,
    running: servers.value?.filter(s => s.status === 'running').length ?? 0,
    stopped: servers.value?.filter(s => s.status === 'stopped').length ?? 0,
}));

const statusVariant = (status: string) => {
    switch (status) {
        case 'running': return 'default';
        case 'stopped': return 'destructive';
        default: return 'secondary';
    }
};
</script>

<template>
    <div class="space-y-6 animate-fade-in">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-white">Dashboard</h1>
            <p class="text-muted-foreground">Welcome back, {{ authStore.user?.name }}</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">Total Servers</p>
                    <p class="text-2xl font-bold text-white">{{ stats.total }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">Running</p>
                    <p class="text-2xl font-bold text-success-500">{{ stats.running }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">Stopped</p>
                    <p class="text-2xl font-bold text-danger-500">{{ stats.stopped }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Recent Servers -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <h2 class="text-lg font-semibold text-white">Your Servers</h2>
                <RouterLink to="/servers" class="text-sm text-primary hover:text-primary/80">
                    View all →
                </RouterLink>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="isLoading" class="p-6 text-center text-muted-foreground">
                    Loading...
                </div>
                <div v-else-if="!servers?.length" class="p-6 text-center">
                    <ServerStackIcon class="w-12 h-12 mx-auto mb-4 text-muted" />
                    <p class="text-muted-foreground">You don't have any servers yet.</p>
                </div>
                <table v-else class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Resources</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="server in servers?.slice(0, 5)" :key="server.id">
                            <td class="font-medium text-white">{{ server.name }}</td>
                            <td>
                                <Badge :variant="statusVariant(server.status)">{{ server.status }}</Badge>
                            </td>
                            <td>{{ server.node?.location?.short_code || '-' }}</td>
                            <td class="text-sm text-muted-foreground">
                                {{ server.cpu }} vCPU, {{ server.memory_formatted }}
                            </td>
                            <td>
                                <RouterLink 
                                    :to="`/servers/${server.uuid}`"
                                    class="text-primary hover:text-primary/80"
                                >
                                    Manage →
                                </RouterLink>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
