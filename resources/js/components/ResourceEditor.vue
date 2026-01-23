<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { adminServerApi } from '@/api/servers';
import { useToast } from '@/composables/useToast';

interface Server {
    id: number;
    cpu: number;
    memory: number;
    disk: number;
    bandwidth_limit: number | null;
}

const props = defineProps<{
    server: Server;
}>();

const emit = defineEmits(['success']);
const queryClient = useQueryClient();
const toast = useToast();

// Form state with custom values
const resources = ref({
    cpu: props.server.cpu,
    memory: props.server.memory,
    disk: props.server.disk,
    bandwidth_limit: props.server.bandwidth_limit || 0,
});

// GB/TB conversions for UI
const memoryGB = computed({
    get: () => Math.round(resources.value.memory / 1024 / 1024 / 1024),
    set: (val) => { resources.value.memory = val * 1024 * 1024 * 1024; }
});

const diskGB = computed({
    get: () => Math.round(resources.value.disk / 1024 / 1024 / 1024),
    set: (val) => { resources.value.disk = val * 1024 * 1024 * 1024; }
});

const bandwidthTB = computed({
    get: () => resources.value.bandwidth_limit ? Math.round(resources.value.bandwidth_limit / 1024 / 1024 / 1024 / 1024) : 0,
    set: (val) => { resources.value.bandwidth_limit = val > 0 ? val * 1024 * 1024 * 1024 * 1024 : null; }
});

const minDiskGB = computed(() => Math.round(props.server.disk / 1024 / 1024 / 1024));

// Check for changes
const hasChanges = computed(() => {
    return resources.value.cpu !== props.server.cpu ||
        resources.value.memory !== props.server.memory ||
        resources.value.disk !== props.server.disk ||
        resources.value.bandwidth_limit !== (props.server.bandwidth_limit || 0);
});

// Reset to original values
const reset = () => {
    resources.value = {
        cpu: props.server.cpu,
        memory: props.server.memory,
        disk: props.server.disk,
        bandwidth_limit: props.server.bandwidth_limit || 0,
    };
};

// Save mutation
const { mutate: saveResources, isPending: isSaving } = useMutation({
    mutationFn: () => adminServerApi.updateResources(props.server.id, resources.value),
    onSuccess: () => {
        toast.success('Resources updated successfully. Please reboot the server for changes to take effect.');
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers', props.server.id] });
        emit('success');
    },
    onError: (error: any) => {
        toast.error(error.response?.data?.message || 'Failed to update resources');
    },
});

// Reset when server changes
watch(() => props.server, reset, { deep: true });
</script>

<template>
    <div class="space-y-6">
        <div>
            <h3 class="text-lg font-semibold text-white mb-2">Resource Allocation</h3>
            <p class="text-sm text-secondary-400">Customize resource allocations for this server. Server must be
                rebooted after changes.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- CPU Cores -->
            <div class="glass-card border border-secondary-700/50 rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-primary-500/10 rounded-lg">
                        <svg class="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white">CPU Cores</h4>
                        <p class="text-xs text-secondary-400">Processing power</p>
                    </div>
                </div>
                <div>
                    <label class="label">Cores</label>
                    <input v-model.number="resources.cpu" type="number" min="1" max="64" class="input" />
                    <p class="text-xs text-secondary-500 mt-1">Current: {{ server.cpu }} cores</p>
                </div>
            </div>

            <!-- Memory (RAM) -->
            <div class="glass-card border border-secondary-700/50 rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-primary-500/10 rounded-lg">
                        <svg class="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white">Memory (RAM)</h4>
                        <p class="text-xs text-secondary-400">System memory</p>
                    </div>
                </div>
                <div>
                    <label class="label">GB</label>
                    <input v-model.number="memoryGB" type="number" min="1" max="256" class="input" />
                    <p class="text-xs text-secondary-500 mt-1">Current: {{ Math.round(server.memory / 1024 / 1024 /
                        1024) }} GB</p>
                </div>
            </div>

            <!-- Disk Storage -->
            <div class="glass-card border border-secondary-700/50 rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-primary-500/10 rounded-lg">
                        <svg class="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white">Disk Storage</h4>
                        <p class="text-xs text-secondary-400">Storage capacity</p>
                    </div>
                </div>
                <div>
                    <label class="label">GB</label>
                    <input v-model.number="diskGB" type="number" :min="minDiskGB" max="2000" class="input" />
                    <p class="text-xs text-warning-400 mt-1">⚠ Cannot be decreased (min: {{ minDiskGB }} GB)</p>
                    <p class="text-xs text-secondary-500">Current: {{ Math.round(server.disk / 1024 / 1024 / 1024)
                        }} GB</p>
                </div>
            </div>

            <!-- Bandwidth Limit -->
            <div class="glass-card border border-secondary-700/50 rounded-xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-primary-500/10 rounded-lg">
                        <svg class="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white">Bandwidth Limit</h4>
                        <p class="text-xs text-secondary-400">Monthly bandwidth cap</p>
                    </div>
                </div>
                <div>
                    <label class="label">TB (0 for unlimited)</label>
                    <input v-model.number="bandwidthTB" type="number" min="0" max="100" class="input" />
                    <p class="text-xs text-secondary-500 mt-1">
                        Current: {{ server.bandwidth_limit ? Math.round(server.bandwidth_limit / 1024 / 1024 / 1024
                            / 1024) + ' TB' : 'Unlimited' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div
            class="flex items-center justify-between p-4 bg-secondary-800/50 rounded-lg border border-secondary-700">
            <div class="text-sm">
                <span v-if="hasChanges" class="text-warning-400">• Unsaved changes</span>
                <span v-else class="text-secondary-400">No changes</span>
            </div>
            <div class="flex gap-3">
                <button v-if="hasChanges" @click="reset" class="btn btn-secondary">
                    Reset
                </button>
                <button @click="saveResources" :disabled="!hasChanges || isSaving"
                    class="btn btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                    <span v-if="isSaving">Saving...</span>
                    <span v-else>Save Changes</span>
                </button>
            </div>
        </div>
    </div>
</template>
