<script setup lang="ts">
import { ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import api from '@/lib/axios';

const currentPage = ref(1);

// Fetch activity logs
const { data: logs, isLoading } = useQuery({
    queryKey: ['admin', 'activity-logs', currentPage],
    queryFn: async () => {
        const response = await api.get('/admin/activity-logs', {
            params: { page: currentPage.value }
        });
        return response.data;
    },
});

const getActionColor = (description: string) => {
    if (description.includes('created')) return 'default';
    if (description.includes('updated')) return 'secondary';
    if (description.includes('deleted')) return 'destructive';
    return 'secondary';
};
</script>

<template>
    <div class="p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Activity Logs</h1>
            <p class="text-muted-foreground">Monitor system activity and changes</p>
        </div>

        <Card>
            <CardHeader>
                <h2 class="text-lg font-semibold text-white">Recent Activity</h2>
            </CardHeader>
            <CardContent>
                <div v-if="isLoading" class="text-center py-8 text-muted-foreground">
                    Loading activity logs...
                </div>
                <div v-else-if="!logs?.data?.length" class="text-center py-8 text-muted-foreground">
                    No activity logs found.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="log in logs.data" :key="log.id" 
                         class="flex items-start gap-4 p-4 border border-border rounded-lg">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <Badge :variant="getActionColor(log.description)">
                                    {{ log.description }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    {{ new Date(log.created_at).toLocaleString() }}
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                By: {{ log.causer?.name || 'System' }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
