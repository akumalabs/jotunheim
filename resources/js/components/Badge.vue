<script setup lang="ts">
import { computed } from 'vue';

export interface BadgeProps {
    label: string;
    size?: 'sm' | 'md' | 'lg';
    type?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info';
    pill?: boolean;
    count?: number;
}

export default defineComponent({
    name: 'Badge',
    props: defineProps<BadgeProps>(),
    setup(props) {
        const sizeClasses = {
            sm: 'text-xs px-2.5 py-0.5 rounded-full',
            md: 'text-sm px-3 py-1 rounded-lg',
            lg: 'text-base px-4 py-1.5 rounded-xl',
        };

        const typeClasses = {
            primary: 'bg-primary-500/30 text-white',
            secondary: 'bg-secondary-600/30 text-white',
            success: 'bg-success-500/20 text-white',
            warning: 'bg-warning-500/20 text-white',
            danger: 'bg-danger-500/30 text-white',
            info: 'bg-primary-500/30 text-white',
        };

        const baseClasses = computed(() => {
            return [
                sizeClasses[props.size || 'md'],
                typeClasses[props.type || 'secondary'],
                props.pill ? 'rounded-full' : '',
            ];
        });

        return {
            baseClasses,
        };
    },
});
</script>

<template>
    <div :class="baseClasses">
        <span v-if="props.count">{{ props.count }}</span>
        <slot></slot>
    </div>
</template>

<style scoped>
.rounded-full {
    @apply 'rounded-full';
}
</style>
