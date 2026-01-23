<script setup lang="ts">
import { computed } from 'vue';

interface DataCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    trend?: 'up' | 'down' | 'neutral';
    trendValue?: string;
    icon?: any;
    size?: 'sm' | 'md' | 'lg';
    color?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
    loading?: boolean;
}

const props = withDefaults(defineProps<DataCardProps>(), {
    size: 'md',
    color: 'secondary',
    loading: false,
});

const formattedValue = computed(() => {
    if (typeof props.value === 'number') {
        return props.value.toLocaleString();
    }
    return props.value;
});

const getTrendIcon = () => {
    if (props.trend === 'up') return '↑';
    if (props.trend === 'down') return '↓';
    return '→';
};

const getTrendClass = () => {
    if (props.trend === 'up') return 'text-success-500';
    if (props.trend === 'down') return 'text-danger-500';
    return 'text-secondary-400';
};

const getTrendColor = () => {
    if (props.trend === 'up') return 'bg-success-500/20';
    if (props.trend === 'down') return 'bg-danger-500/20';
    return 'bg-secondary-700/50';
};

const sizeClasses: Record<string, string> = {
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-8',
};

const colorClasses: Record<string, string> = {
    primary: 'border-primary-500/30 hover:border-primary-500',
    secondary: 'border-secondary-600/30 hover:border-secondary-500',
    success: 'border-success-500/30 hover:border-success-400',
    warning: 'border-warning-500/30 hover:border-warning-400',
    danger: 'border-danger-500/30 hover:border-danger-400',
};

const colorBgClasses: Record<string, string> = {
    primary: 'bg-primary-500/20',
    secondary: 'bg-secondary-700/30',
    success: 'bg-success-500/20',
    warning: 'bg-warning-500/20',
    danger: 'bg-danger-500/20',
};
</script>

<template>
    <div
        class="glass-card rounded-xl border"
        :class="[sizeClasses[size], colorClasses[color]]"
    >
        <div class="flex items-start gap-4">
            <div
                v-if="icon"
                class="flex-shrink-0"
                :class="colorBgClasses"
            >
                <component :is="icon" class="w-6 h-6" />
            </div>

            <div class="flex-1 min-w-0">
                <h3 class="text-lg font-semibold text-white mb-1">
                    {{ title }}
                </h3>
                <p class="text-sm text-secondary-400">
                    {{ subtitle }}
                </p>

                <div class="mt-4">
                    <div
                        v-if="!loading"
                        class="text-3xl font-bold text-white"
                    >
                        {{ formattedValue }}
                    </div>
                    <div
                        v-else
                        class="h-12"
                    ></div>
                </div>

                <div
                    v-if="trend"
                    class="flex items-center gap-1 mt-2"
                >
                    <span
                        :class="[getTrendClass()]"
                    >
                        {{ getTrendIcon() }}
                        {{ trendValue || '' }}
                    </span>
                    <div
                        class="text-xs px-2 py-1 rounded-full"
                        :class="[getTrendColor()]"
                    >
                        {{ trendValue || '' }}%
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
</style>
