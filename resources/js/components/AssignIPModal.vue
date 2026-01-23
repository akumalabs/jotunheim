<script setup lang="ts">
import { ref, computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { adminServerApi } from '@/api/servers';
import BaseModal from './BaseModal.vue';
import { GlobeAltIcon } from '@heroicons/vue/24/outline';
import LoadingSpinner from './LoadingSpinner.vue';

const props = defineProps<{
    open: boolean;
    serverId: number;
}>();

const emit = defineEmits<{
    close: [];
    success: [];
}>();

const queryClient = useQueryClient();
const selectedAddressId = ref<number | null>(null);

// Fetch available IPs from server's node
const { data: response, isLoading } = useQuery({
    queryKey: ['server-available-ips', props.serverId],
    queryFn: () => adminServerApi.getAvailableIPs(props.serverId),
    enabled: () => props.open,
});

const ipsList = computed(() => response.value?.data || []);

// Assign IP mutation
const assignMutation = useMutation({
    mutationFn: () => adminServerApi.assignIP(props.serverId, {
        address_id: selectedAddressId.value!,
    }),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers', props.serverId] });
        selectedAddressId.value = null;
        emit('success');
        emit('close');
    },
    onError: (error: any) => {
        alert(error.response?.data?.message || 'Failed to assign IP address');
    },
});

const handleAssign = () => {
    if (!selectedAddressId.value) {
        alert('Please select an IP address');
        return;
    }
    assignMutation.mutate();
};
</script>

<template>
    <BaseModal :open="open" title="Assign IP Address" size="md" @close="$emit('close')">
        <div class="space-y-4">
            <label class="label">
                Available IP Addresses ({{ ipsList.length }})
            </label>
            
            <div v-if="isLoading" class="flex justify-center py-12">
                <LoadingSpinner text="Loading available IPs..." />
            </div>
            
            <div v-else-if="ipsList.length === 0" class="text-center py-12">
                <GlobeAltIcon class="w-12 h-12 mx-auto text-secondary-600 mb-3" />
                <p class="text-secondary-400">No available IP addresses for this node</p>
            </div>
            
            <div v-else class="max-h-96 overflow-y-auto space-y-2 pr-2">
                <label 
                    v-for="ip in ipsList" 
                    :key="ip.id"
                    class="flex items-center gap-3 p-4 rounded-lg cursor-pointer transition-all duration-200 border"
                    :class="selectedAddressId === ip.id 
                        ? 'bg-primary-500/20 border-primary-500/50 ring-1 ring-primary-500/30' 
                        : 'bg-secondary-800/50 hover:bg-secondary-800 border-secondary-700 hover:border-secondary-600'"
                >
                    <input 
                        type="radio" 
                        :value="ip.id" 
                        v-model="selectedAddressId"
                        class="w-4 h-4 text-primary-500 focus:ring-primary-500"
                    >
                    <GlobeAltIcon 
                        class="w-5 h-5" 
                        :class="ip.type === 'ipv4' ? 'text-blue-400' : 'text-purple-400'" 
                    />
                    <div class="flex-1">
                        <div class="text-sm font-medium text-white font-mono">
                            {{ ip.address }}
                        </div>
                    </div>
                    <span 
                        class="px-2 py-1 text-xs rounded font-medium"
                        :class="ip.type === 'ipv4' 
                            ? 'bg-blue-500/20 text-blue-300' 
                            : 'bg-purple-500/20 text-purple-300'"
                    >
                        {{ ip.type.toUpperCase() }}
                    </span>
                </label>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3">
                <button 
                    @click="$emit('close')" 
                    class="btn btn-secondary"
                    :disabled="assignMutation.isPending.value"
                >
                    Cancel
                </button>
                <button 
                    @click="handleAssign" 
                    :disabled="!selectedAddressId || assignMutation.isPending.value"
                    class="btn btn-primary"
                >
                    <span v-if="assignMutation.isPending.value">Assigning...</span>
                    <span v-else>Assign IP</span>
                </button>
            </div>
        </template>
    </BaseModal>
</template>
