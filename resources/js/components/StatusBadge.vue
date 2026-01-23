<script setup lang="ts">
export interface StatusBadgeProps {
    status: string;
    size?: 'sm' | 'md' | 'lg';
    showText?: boolean;
}

export default defineComponent({
    name: 'StatusBadge',
    props: defineProps<StatusBadgeProps>(),
    setup(props) {
        const statusConfig = {
            running: {
                bg: 'bg-success-500/20',
                text: 'text-success-100',
                dot: 'bg-success-500',
                icon: '●'
            },
            stopped: {
                bg: 'bg-secondary-700/50',
                text: 'text-secondary-100',
                dot: 'bg-secondary-600',
                icon: '○'
            },
            installing: {
                bg: 'bg-warning-500/20',
                text: 'text-warning-100',
                dot: 'bg-warning-500',
                icon: '◐'
            },
            suspended: {
                bg: 'bg-danger-500/20',
                text: 'text-danger-100',
                dot: 'bg-danger-500',
                icon: '●'
            },
            pending: {
                bg: 'bg-secondary-700/50',
                text: 'text-secondary-100',
                dot: 'bg-secondary-600',
                icon: '◌'
            },
            failed: {
                bg: 'bg-danger-500/20',
                text: 'text-danger-100',
                dot: 'bg-danger-500',
                icon: '✕'
            },
        };

        const sizeClasses = {
            sm: 'text-xs px-2 py-0.5',
            md: 'text-sm px-2.5 py-1',
            lg: 'text-base px-3 py-1.5',
        };

        const config = statusConfig[props.status.toLowerCase()] || statusConfig.pending;

        return {
            config,
            sizeClasses: sizeClasses[props.size || 'md'],
        };
    },
});
</script>

<template>
    <span
        :class="[config.bg, config.text, sizeClasses]"
        class="inline-flex items-center gap-1.5 rounded-full font-medium"
    >
        <span class="animate-pulse">
            {{ config.icon }}
        </span>
        <span v-if="showText" class="capitalize">
            {{ status }}
        </span>
    </span>
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
</style>
