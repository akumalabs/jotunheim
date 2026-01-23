<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm } from 'vee-validate';
import * as zod from 'zod';
import { XMarkIcon } from '@heroicons/vue';
import { useApi } from '@/api/servers';

interface ResizeProps {
    server: ServerData;
    open: boolean;
    onResize: () => void;
}

interface ServerData {
    id: string;
    uuid: string;
    name: string;
    hostname: string;
    cpu: number;
    memory: number;
    disk: number;
    bandwidth_limit: number;
    bandwidth_usage: number;
    status: string;
    is_suspended: boolean;
}

const schema = zod.object({
    cpu: zod.number().min(1).max(32).optional(),
    memory: zod.number().min(512).max(1048576).optional(),
    disk: zod.number().min(10).max(10240).optional(),
});

export default defineComponent({
    name: 'ResizeModal',
    props: defineProps<ResizeProps>(),
    setup(props) {
        const api = useApi();

        const { handleSubmit, values, errors, resetForm } = useForm({
            validationSchema: schema,
        initialValues: {
            cpu: props.server.cpu,
            memory: props.server.memory,
            disk: props.server.disk,
        },
        onSubmit: async (values) => {
            try {
                await api.resize(props.server.uuid, values);
                props.open = false;
                props.onResize?.();
            } catch (error) {
                console.error('Resize failed:', error);
            }
        },
    });

        const maxCpu = computed(() => 32);
        const maxMemory = computed(() => 1048576);
        const maxDisk = computed(() => 10240);

        const getCpuProgress = () => Math.round((values.cpu / props.server.cpu) * 100);
        const getMemoryProgress = () => Math.round((values.memory / props.server.memory) * 100);
        const getDiskProgress = () => Math.round((values.disk / props.server.disk) * 100);

        const getCpuStatus = (value: number, current: number) => {
            const percentage = (value / current) * 100;
            if (percentage <= 50) return 'low';
            if (percentage <= 75) return 'medium';
            return 'high';
        return 'severe';
        };

        const getMemoryStatus = (value: number, current: number) => {
            const percentage = (value / current) * 100;
            if (percentage <= 50) return 'low';
            if (percentage <= 75) return 'medium';
            return 'high';
            return 'severe';
        };

        const getDiskStatus = (value: number, current: number) => {
            const percentage = (value / current) * 100;
            if (percentage <= 50) return 'low';
            if (percentage <= 75) return 'medium';
            return 'high';
            return 'severe';
        };

        return () => resetForm();
    },
});
</script>

<script setup lang="ts">
import { useForm } from 'vee-validate';

const api = useApi();

const schema = zod.object({
    cpu: zod.number().min(1).max(32).optional(),
    memory: zod.number().min(512).max(1048576).optional(),
    disk: zod.number().min(10).max(10240).optional(),
});

const { handleSubmit, values, errors, resetForm } = useForm({
    validationSchema: schema,
    initialValues: {},
    onSubmit: async (values) => {
        try {
            await api.resize(props.server.uuid, values);
            props.open = false;
            props.onResize?.();
        } catch (error) {
            console.error('Resize failed:', error);
        }
    },
});

const maxCpu = 32;
const maxMemory = 1048576;
const maxDisk = 10240;

const getCpuProgress = () => Math.round((values.cpu / props.server.cpu) * 100);
const getMemoryProgress = () => Math.round((values.memory / props.server.memory) * 100);
const getDiskProgress = () => Math.round((values.disk / props.server.disk) * 100);

const getCpuStatus = (value, current) => {
    const percentage = (value / current) * 100;
    if (percentage <= 50) return 'low';
    if (percentage <= 75) return 'medium';
    return 'high';
    return 'severe';
};

const getMemoryStatus = (value, current) => {
    const percentage = (value / current) * 100;
    if (percentage <= 50) return 'low';
    if (percentage <= 75) return 'medium';
    return 'high';
    return 's
</script>

<style scoped>
.resize-form {
    @apply 'flex flex-col gap-4';
}

.form-row {
    @apply 'flex items-center justify-between';
}

.form-label {
    @apply 'text-sm font-medium text-secondary-300 mb-1';
}

.form-input {
    @apply 'w-full px-4 py-2 bg-secondary-900 border border-secondary-700 rounded-lg text-secondary-100 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:bg-secondary-800 disabled:cursor-not-allowed';
}

.form-input:disabled {
    @apply 'bg-secondary-800 text-secondary-500 cursor-not-allowed';
}

.form-actions {
    @apply 'flex justify-end gap-2';
}

.progress-bar {
    @apply 'h-2 bg-secondary-800 rounded-full overflow-hidden';
}

.progress-fill {
    @apply 'h-full bg-primary-600 rounded-full transition-all duration-300';
}

.progress-text {
    @apply 'absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-xs font-medium text-white';
}

.status-badge {
    @apply 'inline-flex items-center gap-2 px-2 py-1 rounded-full text-xs font-medium';
}

.status-badge-low {
    @apply 'bg-success-500/20 text-success-100';
}

.status-badge-medium {
    @apply 'bg-warning-500/20 text-warning-100';
}

.status-badge-high {
    @apply 'bg-danger-500/20 text-danger-100';
}

.status-badge-severe {
    @apply 'bg-danger-600/20 text-white';
}

.info-row {
    @apply 'flex items-center gap-2 text-xs text-secondary-400';
}

.info-icon {
    @apply 'w-4 h-4';
}

.info-label {
    @apply 'text-secondary-400';
}
</style>

<template>
    <div class="resize-form">
        <div class="form-row">
            <label class="form-label">
                CPU Cores
            </label>
            <input
                type="number"
                v-model.number="values.cpu"
                :max="maxCpu"
                :disabled="form.isSubmitting"
                class="form-input"
                placeholder="Current: {{ props.server.cpu }}"
            />
            <div class="info-row">
                <XMarkIcon class="info-icon" />
                <span class="info-label">{{ props.server.cpu }} cores</span>
            </div>
            <div v-if="errors.cpu" class="status-badge status-badge-severe">
                {{ errors.cpu }}
            </div>
        </div>

        <div class="form-row">
            <label class="form-label">
                Memory (MB)
            </label>
            <input
                type="number"
                v-model.number="values.memory"
                :max="maxMemory"
                :disabled="form.isSubmitting"
                class="form-input"
                placeholder="Current: {{ props.server.memory }}"
            />
            <div class="info-row">
                <XMarkIcon class="info-icon" />
                <span class="info-label">{{ props.server.memory }} MB</span>
            </div>
            <div v-if="errors.memory" class="status-badge status-badge-severe">
                {{ errors.memory }}
            </div>
        </div>

        <div class="form-row">
            <label class="form-label">
                Disk Space (GB)
            </label>
            <input
                type="number"
                v-model.number="values.disk"
                :max="maxDisk"
                :disabled="form.isSubmitting"
                class="form-input"
                placeholder="Current: {{ props.server.disk }}"
            />
            <div class="info-row">
                <XMarkIcon class="info-icon" />
                <span class="info-label">{{ props.server.disk }} GB</span>
            </div>
            <div v-if="errors.disk" class="status-badge status-badge-severe">
                {{ errors.disk }}
            </div>
        </div>

        <div class="form-actions">
            <button
                type="button"
                @click="resetForm"
                class="btn-secondary"
                :disabled="form.isSubmitting"
            >
                Cancel
            </button>
            <button
                type="submit"
                :disabled="form.isSubmitting || !values.cpu && !values.memory && !values.disk"
                class="btn-primary"
                @click="handleSubmit"
            >
                Resize Server
            </button>
        </div>
    </div>
</template>
