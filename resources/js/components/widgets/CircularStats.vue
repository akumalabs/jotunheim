<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps({
    value: {
        type: Number,
        required: true,
        default: 0
    },
    max: {
        type: Number,
        default: 100
    },
    title: {
        type: String,
        default: ''
    },
    subtitle: {
        type: String,
        default: ''
    },
    icon: {
        type: Function, // Component
        default: null
    },
    color: {
        type: String,
        default: 'primary', // primary, success, warning, danger
        validator: (val: string) => ['primary', 'success', 'warning', 'danger', 'info', 'secondary'].includes(val)
    },
    size: {
        type: String, // sm, md, lg
        default: 'md'
    }
});

const percentage = computed(() => Math.min(Math.max((props.value / props.max) * 100, 0), 100));

// Circle calculations
const radius = 36;
const circumference = 2 * Math.PI * radius;
const dashOffset = computed(() => circumference - (percentage.value / 100) * circumference);

// Color mapping
const colorClass = computed(() => {
    switch (props.color) {
        case 'success': return 'text-success-500 stroke-success-500';
        case 'warning': return 'text-warning-500 stroke-warning-500';
        case 'danger': return 'text-danger-500 stroke-danger-500';
        case 'info': return 'text-blue-500 stroke-blue-500';
        case 'secondary': return 'text-secondary-400 stroke-secondary-400';
        default: return 'text-primary-500 stroke-primary-500';
    }
});

const bgClass = computed(() => {
    switch (props.color) {
        case 'success': return 'stroke-success-500/20';
        case 'warning': return 'stroke-warning-500/20';
        case 'danger': return 'stroke-danger-500/20';
        case 'info': return 'stroke-blue-500/20';
        case 'secondary': return 'stroke-secondary-400/20';
        default: return 'stroke-primary-500/20';
    }
});
</script>

<template>
    <div class="flex items-center p-4 bg-secondary-900/50 border border-secondary-800 rounded-xl">
        <!-- Circular Progress -->
        <div class="relative w-20 h-20 flex-shrink-0 mr-4">
            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 80 80">
                <!-- Background Circle -->
                <circle
                    cx="40"
                    cy="40"
                    :r="radius"
                    fill="transparent"
                    stroke-width="6"
                    :class="bgClass"
                />
                <!-- Progress Circle -->
                <circle
                    cx="40"
                    cy="40"
                    :r="radius"
                    fill="transparent"
                    stroke-width="6"
                    stroke-linecap="round"
                    :class="colorClass"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="dashOffset"
                    class="transition-all duration-1000 ease-out"
                />
            </svg>
            <!-- Center Text/Icon -->
            <div class="absolute inset-0 flex items-center justify-center">
                <component 
                    v-if="icon" 
                    :is="icon" 
                    class="w-6 h-6" 
                    :class="colorClass.replace('stroke-', 'text-')" 
                />
                <span v-else class="text-sm font-bold text-white">{{ Math.round(percentage) }}%</span>
            </div>
        </div>

        <!-- Info -->
        <div>
            <div class="text-secondary-400 text-sm font-medium mb-1">{{ title }}</div>
            <div class="text-xl font-bold text-white tracking-tight">{{ value }} <span class="text-xs text-secondary-500 font-normal">/ {{ max }}</span></div>
            <div v-if="subtitle" class="text-xs text-secondary-500 mt-1">{{ subtitle }}</div>
        </div>
    </div>
</template>
