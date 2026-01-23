<script setup lang="ts">
import { ref, computed } from 'vue';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { firewallApi } from '@/api/firewall';
import BaseModal from './BaseModal.vue';

const props = defineProps<{
    open: boolean;
    serverId: number;
    rule?: any;
}>();

const emit = defineEmits<{
    close: [];
    success: [];
}>();

const queryClient = useQueryClient();

const formData = ref({
    name: props.rule?.name || '',
    direction: props.rule?.direction || 'both',
    action: props.rule?.action || 'allow',
    protocol: props.rule?.protocol || 'tcp',
    source_address: props.rule?.source_address || '',
    source_port: props.rule?.source_port || null,
    dest_address: props.rule?.dest_address || '',
    dest_port: props.rule?.dest_port || null,
    ip_version: props.rule?.ip_version || 'ipv4',
    priority: props.rule?.priority || 515,
    enabled: props.rule?.enabled ?? true,
});

const isEditing = computed(() => !!props.rule);

const saveMutation = useMutation({
    mutationFn: () => {
        if (isEditing.value) {
            return firewallApi.updateRule(props.serverId, props.rule.id, formData.value);
        }
        return firewallApi.createRule(props.serverId, formData.value);
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['firewall-rules', props.serverId] });
        emit('success');
        emit('close');
    },
    onError: (error: any) => {
        alert(error.response?.data?.message || 'Failed to save firewall rule');
    },
});
</script>

<template>
    <BaseModal 
        :open="open" 
        :title="isEditing ? 'Edit Firewall Rule' : 'Add Firewall Rule'" 
        size="lg"
        @close="$emit('close')"
    >
        <div class="space-y-4">
            <!-- Name -->
            <div>
                <label class="label">Rule Name (Optional)</label>
                <input 
                    v-model="formData.name" 
                    type="text" 
                    class="input"
                    placeholder="e.g., Allow HTTP"
                >
            </div>

            <!-- Direction & Action -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Direction</label>
                    <select v-model="formData.direction" class="input">
                        <option value="in">Incoming</option>
                        <option value="out">Outgoing</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                <div>
                    <label class="label">Action</label>
                    <select v-model="formData.action" class="input">
                        <option value="allow">Allow</option>
                        <option value="deny">Deny</option>
                    </select>
                </div>
            </div>

            <!-- Protocol & IP Version -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Protocol</label>
                    <select v-model="formData.protocol" class="input">
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                        <option value="icmp">ICMP</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div>
                    <label class="label">IP Version</label>
                    <select v-model="formData.ip_version" class="input">
                        <option value="ipv4">IPv4</option>
                        <option value="ipv6">IPv6</option>
                        <option value="both">Both</option>
                    </select>
                </div>
            </div>

            <!-- Source -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Source Address</label>
                    <input 
                        v-model="formData.source_address" 
                        type="text" 
                        class="input"
                        placeholder="0.0.0.0/0 (any)"
                    >
                </div>
                <div>
                    <label class="label">Source Port</label>
                    <input 
                        v-model.number="formData.source_port" 
                        type="number" 
                        class="input"
                        placeholder="Any"
                        min="1"
                        max="65535"
                    >
                </div>
            </div>

            <!-- Destination -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Destination Address</label>
                    <input 
                        v-model="formData.dest_address" 
                        type="text" 
                        class="input"
                        placeholder="0.0.0.0/0 (any)"
                    >
                </div>
                <div>
                    <label class="label">Destination Port</label>
                    <input 
                        v-model.number="formData.dest_port" 
                        type="number" 
                        class="input"
                        placeholder="e.g., 80, 443"
                        min="1"
                        max="65535"
                    >
                </div>
            </div>

            <!-- Priority & Enabled -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Priority (500-520)</label>
                    <input 
                        v-model.number="formData.priority" 
                        type="number" 
                        class="input"
                        min="500"
                        max="520"
                    >
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input 
                            v-model="formData.enabled" 
                            type="checkbox" 
                            class="w-4 h-4 text-primary-500"
                        >
                        <span class="text-sm text-white">Rule Enabled</span>
                    </label>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3">
                <button 
                    @click="$emit('close')" 
                    class="btn btn-secondary"
                    :disabled="saveMutation.isPending.value"
                >
                    Cancel
                </button>
                <button 
                    @click="saveMutation.mutate()" 
                    :disabled="saveMutation.isPending.value"
                    class="btn btn-primary"
                >
                    {{ saveMutation.isPending.value ? 'Saving...' : (isEditing ? 'Update Rule' : 'Add Rule') }}
                </button>
            </div>
        </template>
    </BaseModal>
</template>
