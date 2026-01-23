<script setup lang="ts">
import { computed } from 'vue';

interface ServerCardProps {
    server: {
        id: number | string;
        uuid: string;
        name: string;
        hostname?: string;
        status: string;
        cpu: number;
        memory: number;
        disk: number;
        ip?: string;
        vmid?: string | number;
        bandwidth_usage?: number;
        bandwidth_limit?: number;
        is_suspended?: boolean;
    };
    clickable?: boolean;
}

const props = withDefaults(defineProps<ServerCardProps>(), {
    clickable: false,
});

const emit = defineEmits<{
    (e: 'click', uuid: string): void;
}>();

const handleClick = () => {
    if (props.clickable) {
        emit('click', props.server.uuid);
    }
};

const statusConfig = {
    running: {
        bg: 'bg-success-500/20',
        text: 'text-success-100',
        label: 'Running',
        dot: '●'
    },
    stopped: {
        bg: 'bg-secondary-700/50',
        text: 'text-secondary-100',
        label: 'Stopped',
        dot: '○'
    },
    installing: {
        bg: 'bg-warning-500/20',
        text: 'text-warning-100',
        label: 'Installing',
        dot: '◐'
    },
    suspended: {
        bg: 'bg-danger-500/20',
        text: 'text-danger-100',
        label: 'Suspended',
        dot: '●'
    },
};

const config = computed(() => statusConfig[props.server.status.toLowerCase() as keyof typeof statusConfig] || statusConfig.stopped);

const bandwidthPercentage = computed(() => {
    if (!props.server.bandwidth_limit || props.server.bandwidth_limit === 0) return 0;
    return Math.round((props.server.bandwidth_usage || 0) / props.server.bandwidth_limit * 100);
});

const bandwidthStatus = computed(() => {
    const percentage = bandwidthPercentage.value;
    if (percentage >= 90) return { bg: 'bg-danger-500/20', text: 'text-danger-100' };
    if (percentage >= 75) return { bg: 'bg-warning-500/20', text: 'text-warning-100' };
    return { bg: 'bg-success-500/20', text: 'text-success-100' };
});

const formatMemory = (bytes: number) => {
    const gb = bytes / (1024 * 1024 * 1024);
    return `${gb.toFixed(1)} GB`;
};

const formatDisk = (bytes: number) => {
    const gb = bytes / (1024 * 1024 * 1024);
    return `${gb.toFixed(0)} GB`;
};
</script>

<template>
    <div
        @click="handleClick"
        class="glass-card rounded-xl transition-all duration-300 hover:shadow-2xl cursor-pointer"
        :class="{ 'border-secondary-700 hover:border-primary-500': clickable }"
    >
        <div class="p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center gap-1.5 px-2 py-0.5 rounded-full"
                        :class="config.bg"
                    >
                        <span class="animate-pulse text-sm">
                            {{ config.dot }}
                        </span>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold text-lg">
                            {{ server.name }}
                        </h4>
                        <p class="text-sm text-secondary-400">
                            {{ server.hostname || 'No hostname' }}
                        </p>
                    </div>
                </div>

                <span class="text-xs text-secondary-400">
                    {{ server.vmid }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-secondary-400">CPU</span>
                    <span class="text-sm font-medium text-white">{{ server.cpu }} cores</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-secondary-400">RAM</span>
                    <span class="text-sm font-medium text-white">{{ formatMemory(server.memory) }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-secondary-400">Disk</span>
                    <span class="text-sm font-medium text-white">{{ formatDisk(server.disk) }}</span>
                </div>
                <div
                    v-if="server.ip"
                    class="flex items-center gap-2"
                >
                    <span class="text-xs text-secondary-400">IP</span>
                    <span class="text-sm font-mono text-white">{{ server.ip }}</span>
                </div>
            </div>

            <div
                v-if="server.bandwidth_limit && server.bandwidth_limit > 0"
                class="mt-4"
            >
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-secondary-400">Bandwidth</span>
                    <span class="text-xs text-secondary-400">
                        {{ bandwidthPercentage }}%
                    </span>
                </div>

                <div class="w-full bg-secondary-900/50 rounded-lg overflow-hidden">
                    <div
                        class="h-2 transition-all duration-300"
                        :class="bandwidthStatus.bg"
                        :style="{ width: `${bandwidthPercentage}%` }"
                    ></div>
                </div>
            </div>

            <div
                v-if="server.is_suspended"
                class="mt-4 px-3 py-2 bg-danger-500/20 rounded-lg text-center"
            >
                <span class="text-sm font-medium text-danger-100">
                    ⚠ Suspended
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s ease-in-out infinite;
}

.border-secondary-700 {
    border-width: 1px;
    border-style: solid;
}
</style>
