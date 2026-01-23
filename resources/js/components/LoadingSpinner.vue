<script setup lang="ts">
import { computed } from 'vue';

interface LoadingSpinnerProps {
    size?: 'sm' | 'md' | 'lg' | 'xl';
    color?: string;
    text?: string;
    overlay?: boolean;
}

const props = withDefaults(defineProps<LoadingSpinnerProps>(), {
    size: 'md',
    color: 'primary',
    overlay: false,
});

const sizes = {
    sm: { outer: 'w-8 h-8', inner: 'w-5 h-5' },
    md: { outer: 'w-12 h-12', inner: 'w-8 h-8' },
    lg: { outer: 'w-16 h-16', inner: 'w-12 h-12' },
    xl: { outer: 'w-24 h-24', inner: 'w-16 h-16' },
};

const colorClasses = {
    primary: 'border-t-primary-500',
    secondary: 'border-t-secondary-500',
    success: 'border-t-success-500',
    warning: 'border-t-warning-500',
    danger: 'border-t-danger-500',
    white: 'border-t-white',
};

const size = computed(() => sizes[props.size]);
const colorClass = computed(() => colorClasses[props.color as keyof typeof colorClasses] || colorClasses.primary);
</script>

<template>
    <div
        class="flex items-center justify-center"
        :class="{ 'fixed inset-0 z-50 bg-secondary-950/80': overlay }"
    >
        <div class="inline-block relative">
            <div
                class="rounded-full border-4 border-solid animate-spin"
                :class="[size.outer, colorClass]"
            >
                <div class="rounded-full border-2 border-t-transparent border-b-transparent animate-pulse">
                    <div
                        class="rounded-full"
                        :class="[size.inner, colorClass]"
                    ></div>
                </div>
            </div>
            <div
                v-if="text"
                class="mt-3 text-center"
            >
                <p class="text-sm text-secondary-300">
                    {{ text }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

.animate-pulse > div > div {
    animation: pulse 1s ease-in-out infinite;
}
</style>
