<template>
    <BaseModal :open="show" :title="modalTitle" size="lg" :persistent="isRebuilding" @close="handleClose">
        <!-- Template Selection Phase -->
        <div v-if="!isRebuilding && !rebuildComplete" class="space-y-4">
            <form @submit.prevent="startRebuild" class="space-y-4">
                <div class="bg-warning-500/10 border border-warning-500/20 rounded-lg p-4">
                    <p class="text-sm text-warning-400">
                        ⚠️ This will reinstall the server with a fresh operating system. All data will be lost!
                    </p>
                </div>

                <div v-if="!loadingTemplates && templates && templates.length === 0"
                    class="text-center py-6 bg-secondary-800/30 rounded-lg border border-secondary-700/50">
                    <ExclamationTriangleIcon class="w-8 h-8 text-warning-500 mx-auto mb-2" />
                    <p class="text-sm text-secondary-300 font-medium mb-1">No templates found</p>
                    <p class="text-xs text-secondary-500 mb-4">You need to sync templates from the Proxmox node.</p>
                    <button type="button" @click="syncTemplatesMutation.mutate()"
                        :disabled="syncTemplatesMutation.isPending.value" class="btn btn-sm btn-primary">
                        <ArrowPathIcon class="w-4 h-4 mr-1.5"
                            :class="{ 'animate-spin': syncTemplatesMutation.isPending.value }" />
                        {{ syncTemplatesMutation.isPending.value ? 'Syncing...' : 'Sync Templates' }}
                    </button>
                </div>

                <div v-else>
                    <label class="label mb-2">Select Template</label>
                    <select v-model="selectedTemplate" class="input w-full" required
                        :disabled="loadingTemplates || !templates?.length">
                        <option :value="null" disabled>Choose an OS template...</option>
                        <option v-for="template in templates" :key="template.id" :value="template.vmid">
                            {{ template.name }}
                        </option>
                    </select>
                    <p v-if="loadingTemplates" class="text-xs text-secondary-400 mt-1">Loading templates...</p>
                </div>

                <!-- Server Details -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label mb-2">Server Name</label>
                        <input v-model="serverName" type="text" class="input w-full" placeholder="Midgard Server" />
                    </div>
                    <div>
                        <label class="label mb-2">Hostname <span
                                class="text-secondary-500 text-xs">(Optional)</span></label>
                        <input v-model="serverHostname" type="text" class="input w-full"
                            placeholder="server.example.com" />
                    </div>
                </div>

                <!-- Password Input -->
                <div>
                    <label class="label mb-2">New Root Password <span
                            class="text-secondary-500 text-xs">(Optional)</span></label>
                    <input v-model="newPassword" type="password" class="input w-full"
                        placeholder="Leave blank to keep existing" />
                    <p class="text-xs text-secondary-500 mt-1.5">Min 8 chars with uppercase, lowercase, number, and
                        symbol</p>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 justify-end pt-2">
                    <button @click="handleClose" type="button" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" :disabled="!selectedTemplate"
                        class="btn btn-danger disabled:opacity-50 disabled:cursor-not-allowed">
                        <ArrowPathIcon class="w-4 h-4 mr-2" />
                        Rebuild Server
                    </button>
                </div>
            </form>
        </div>

        <!-- Progress Tracking Phase -->
        <div v-else-if="isRebuilding" class="space-y-6">
            <!-- Progress Steps -->
            <div class="glass-card border border-secondary-700/50 rounded-xl p-6 space-y-4">
                <div v-for="(step, index) in steps" :key="index"
                    class="text-sm transition-all duration-300" :class="getStepClass(index)">
                    <div class="flex items-center gap-3">
                        <!-- Status Icon -->
                        <div class="flex-shrink-0">
                            <CheckCircleIcon v-if="index < currentStep" class="w-5 h-5 text-green-400" />
                            <div v-else-if="index === currentStep" class="relative">
                                <div class="w-5 h-5 border-2 border-primary-500 rounded-full animate-spin border-t-transparent"></div>
                            </div>
                            <div v-else class="w-5 h-5 border-2 border-secondary-700 rounded-full"></div>
                        </div>

                        <!-- Step Label -->
                        <span class="flex-grow font-medium" :class="index < currentStep ? 'text-green-400' : index === currentStep ? 'text-primary-400' : 'text-secondary-500'">
                            {{ step.label }}
                        </span>

                        <!-- Real Progress Bar for Installing OS step ONLY -->
                        <div v-if="step.hasProgress && index === currentStep" class="ml-auto flex items-center gap-3">
                            <div class="w-32 h-1.5 bg-secondary-700/50 rounded-full overflow-hidden">
                                <div class="h-full bg-primary-500 transition-all duration-300 ease-out"
                                    :style="{ width: `${installProgressPercent}%` }">
                                </div>
                            </div>
                            <span class="text-lg font-bold font-mono text-primary-400 min-w-[3ch] text-right">{{ Math.round(installProgressPercent) }}%</span>
                        </div>

                        <!-- "Processing" indicator for other active steps -->
                        <span v-else-if="index === currentStep && !step.hasProgress" class="text-xs text-primary-400 animate-pulse ml-auto">
                            Processing...
                        </span>
                    </div>
                </div>
            </div>

            <p class="text-sm text-secondary-400 text-center">
                Please wait while your server is being rebuilt. This may take 2-5 minutes.
            </p>
        </div>

        <!-- Success View -->
        <div v-else-if="rebuildComplete && !rebuildError" class="text-center py-8">
            <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <CheckCircleIcon class="w-10 h-10 text-green-400" />
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Rebuild Complete!</h3>
            <p class="text-secondary-400 mb-6">{{ server.name }} has been successfully rebuilt.</p>
            <button @click="closeAndRefresh" class="btn btn-primary">
                Done
            </button>
        </div>

        <!-- Error View -->
        <div v-else-if="rebuildError" class="text-center py-8">
            <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <XCircleIcon class="w-10 h-10 text-red-400" />
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Rebuild Failed</h3>
            <p class="text-secondary-400 mb-6">{{ rebuildError }}</p>
            <button @click="reset" class="btn btn-secondary">
                Try Again
            </button>
        </div>
    </BaseModal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { adminServerApi, templateApi as adminTemplateApi } from '@/api'; // Renamed templateApi to adminTemplateApi to avoid conflict
import BaseModal from '@/components/BaseModal.vue';
import {
    ArrowPathIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    XCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    show: boolean;
    server: any;
}>();

const emit = defineEmits(['close', 'success']);
const queryClient = useQueryClient();

// Form state
const selectedTemplate = ref<string | null>(null);
const newPassword = ref('');
const serverName = ref(props.server.name);
const serverHostname = ref(props.server.hostname || '');

// Auto-fill hostname from server name if user hasn't typed one
watch(serverName, (newName) => {
    if (!props.server.hostname && (!serverHostname.value || serverHostname.value === newName.toLowerCase().replace(/[^a-z0-9-]/g, '-'))) {
        serverHostname.value = newName.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
    }
});

// Fetch available templates
const { data: templates, isLoading: loadingTemplates } = useQuery({
    queryKey: ['templates', props.server.node_id],
    queryFn: async () => {
        const groups = await adminTemplateApi.listGroups(props.server.node_id);
        // Flatten groups to get all templates
        return groups.flatMap(group => group.templates || []);
    },
    enabled: () => !!props.server?.node_id,
});

const syncTemplatesMutation = useMutation({
    mutationFn: () => adminTemplateApi.syncFromProxmox(props.server.node_id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['templates', props.server.node_id] });
    },
});

// Progress state
const isRebuilding = ref(false);
const rebuildComplete = ref(false);
const rebuildError = ref('');
const progressPercentage = ref(0);
const installProgressPercent = ref(0);
const installProgress = ref<any>(null);

function subOperationCompleted(key: string): boolean {
    return installProgress.value?.subOperations?.[key] === true;
}

// Modal title
const modalTitle = computed(() => {
    if (isRebuilding.value) return 'Rebuilding Server';
    if (rebuildComplete.value) return 'Rebuild Complete';
    if (rebuildError.value) return 'Rebuild Failed';
    return 'Rebuild Server';
});

const steps = [
    {
        label: 'Stopping server',
        key: 'stopping_server',
        hasProgress: false
    },
    {
        label: 'Delete server',
        key: 'deleting_server',
        hasProgress: false
    },
    {
        label: 'Installing OS',
        key: 'installing_os',
        hasProgress: true
    },
    {
        label: 'Configuring resources',
        key: 'configuring_resources',
        hasProgress: false
    },
    {
        label: 'Booting server',
        key: 'booting_server',
        hasProgress: false
    },
    {
        label: 'Finalize',
        key: 'finalizing',
        hasProgress: false
    },
];

const currentStep = ref(0);

const getStepClass = (index: number) => {
    if (index < currentStep.value) return 'text-green-400';
    if (index === currentStep.value) return 'text-primary-400 font-medium';
    return 'text-secondary-500';
};

// Rebuild mutation
const rebuildMutation = useMutation({
    mutationFn: (data: { template_vmid: string; password?: string; name?: string; hostname?: string }) =>
        adminServerApi.rebuild(props.server.id, data),
    onSuccess: () => {
        pollServerStatus();
    },
    onError: (error: any) => {
        rebuildError.value = error.response?.data?.message || 'Failed to start rebuild';
        isRebuilding.value = false;
    },
});

let statusPollTimer: any = null;
const currentStatus = ref('unknown');

function startRebuild() {
    if (!selectedTemplate.value) return;

    isRebuilding.value = true;
    rebuildComplete.value = false; // Reset rebuildComplete
    rebuildError.value = ''; // Reset rebuildError
    installProgressPercent.value = 0;
    currentStatus.value = 'rebuilding';
    currentStep.value = 0;

    const data: any = {
        template_vmid: selectedTemplate.value,
        name: serverName.value,
        hostname: serverHostname.value || serverName.value.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, ''),
    };

    if (newPassword.value) {
        data.password = newPassword.value;
    }

    rebuildMutation.mutate(data);
}

const getStepIndex = (step: string) => {
    switch (step) {
        case 'stopping_server': return 0;
        case 'deleting_server': return 1;
        case 'installing_os': return 2;
        case 'configuring_resources': return 3;
        case 'booting_server': return 4;
        case 'finalizing': return 5;
        case 'completed': return 6;
        default: return -1;
    }
};

function pollServerStatus() {
    statusPollTimer = setInterval(async () => {
        try {
            const statusData = await adminServerApi.status(props.server.id);
            currentStatus.value = statusData.status;

            try {
                const progressData = await adminServerApi.installProgress(props.server.id);

                if (progressData.step) {
                    const newStepIndex = getStepIndex(progressData.step);
                    if (newStepIndex !== -1 && newStepIndex >= currentStep.value) {
                        currentStep.value = newStepIndex;
                    }
                }

                if (progressData.status === 'pending') {
                    installProgressPercent.value = 0;
                } else if (progressData.status === 'running') {
                    if (progressData.hasProgress && progressData.cloneProgress !== undefined) {
                        installProgressPercent.value = progressData.cloneProgress;
                    } else if (progressData.progress !== undefined) {
                        installProgressPercent.value = progressData.progress;
                    }
                } else if (progressData.status === 'completed') {
                    installProgressPercent.value = 100;
                } else if (progressData.status === 'failed') {
                    rebuildError.value = progressData.error || 'Rebuild failed on Proxmox';
                    clearInterval(statusPollTimer);
                    isRebuilding.value = false;
                    return;
                }

                if (progressData.step === 'completed') {
                    clearInterval(statusPollTimer);
                    rebuildComplete.value = true;
                    isRebuilding.value = false;
                    queryClient.invalidateQueries({ queryKey: ['admin', 'servers', props.server.id] });
                }

                if (currentStatus.value === 'running' && currentStep.value >= 4) {
                    clearInterval(statusPollTimer);
                    rebuildComplete.value = true;
                    isRebuilding.value = false;
                    queryClient.invalidateQueries({ queryKey: ['admin', 'servers', props.server.id] });
                }

            } catch (e) {
            }

        } catch (error) {
            console.error('Error polling status:', error);
        }
    }, 1000);
}

function closeAndRefresh() {
    queryClient.invalidateQueries({ queryKey: ['admin', 'servers', props.server.id] });
    emit('success');
    emit('close');
    reset();
}

function handleClose() {
    if (isRebuilding.value) return; // Prevent closing during rebuild
    emit('close');
    reset();
}

function reset() {
    isRebuilding.value = false;
    rebuildComplete.value = false;
    rebuildError.value = '';
    installProgressPercent.value = 0;
    selectedTemplate.value = null;
    newPassword.value = '';
    currentStatus.value = 'unknown';
    currentStep.value = 0;

    if (statusPollTimer) {
        clearInterval(statusPollTimer);
        statusPollTimer = null;
    }
}

// Cleanup on unmount
watch(() => props.show, (newVal) => {
    if (!newVal && statusPollTimer) {
        clearInterval(statusPollTimer);
    }
});
</script>
