<script setup lang="ts">
import { ref } from 'vue';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { nodeApi, templateApi } from '@/api';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

const queryClient = useQueryClient();
const isSyncing = ref(false);

// Fetch templates from all nodes
const { data: templates, isLoading } = useQuery({
    queryKey: ['admin', 'templates'],
    queryFn: async () => {
        // First get all nodes
        const nodes = await nodeApi.list();
        
        // Fetch template groups from each node
        const groupsPromises = nodes.map(node => 
            templateApi.listGroups(node.id)
                .then(groups => groups.map(g => ({ ...g, nodeName: node.name })))
                .catch(err => {
                    console.error(`Failed to fetch templates from ${node.name}:`, err);
                    return [];
                })
        );
        
        const allGroups = (await Promise.all(groupsPromises)).flat();
        
        // Flatten to individual templates with node info
        return allGroups.flatMap(group => 
            group.templates.map(t => ({
                ...t,
                groupName: group.name,
                nodeName: group.nodeName,
            }))
        );
    },
});

const handleSync = async () => {
    if (isSyncing.value) return;
    isSyncing.value = true;
    
    try {
        // First fetch all nodes
        const nodes = await nodeApi.list();
        
        // Sync each node
        const promises = nodes.map(node => 
            templateApi.syncFromProxmox(node.id)
                .catch(err => console.error(`Failed to sync node ${node.name}:`, err))
        );
        
        await Promise.all(promises);
        
        // Refresh templates
        queryClient.invalidateQueries({ queryKey: ['admin', 'templates'] });
        alert('Templates synced successfully from all nodes');
    } catch (error) {
        console.error('Sync failed:', error);
        alert('Failed to sync templates');
    } finally {
        isSyncing.value = false;
    }
};
</script>

<template>
    <div class="p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Templates</h1>
            <p class="text-muted-foreground">Manage VM templates for server deployment</p>
        </div>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white">Available Templates</h2>
                    <Button 
                        @click="handleSync" 
                        :disabled="isSyncing"
                    >
                        <ArrowPathIcon 
                            v-if="isSyncing" 
                            class="w-4 h-4 mr-2 animate-spin" 
                        />
                        {{ isSyncing ? 'Syncing...' : 'Sync Templates' }}
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="isLoading" class="text-center py-8 text-muted-foreground">
                    Loading templates...
                </div>
                <div v-else-if="!templates?.length" class="text-center py-8 text-muted-foreground">
                    No templates found. Sync from nodes to get started.
                </div>
                <div v-else class="space-y-4">
                    <div v-for="template in templates" :key="template.id" 
                         class="flex items-center justify-between p-4 border border-border rounded-lg">
                        <div>
                            <h3 class="font-medium text-white">{{ template.name }}</h3>
                            <p class="text-sm text-muted-foreground">{{ template.groupName }} • VMID {{ template.vmid }}</p>
                        </div>
                        <Badge>{{ template.nodeName || 'Unknown' }}</Badge>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
