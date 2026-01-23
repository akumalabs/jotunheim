<script setup lang="ts">
import { computed, PropType } from 'vue';

interface StatDetail {
    label: string;
    value: number | string;
    color?: string; // e.g., 'text-success-500'
}

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
        required: true
    },
    subtitle: {
        type: String,
        default: ''
    },
    icon: {
        type: Function,
        default: null
    },
    color: {
        type: String,
        default: 'primary',
        validator: (val: string) => ['primary', 'success', 'warning', 'danger', 'info', 'secondary'].includes(val)
    },
    details: {
        type: Array as PropType<StatDetail[]>,
        default: () => []
    }
});

const percentage = computed(() => {
    if (props.max === 0) return 0;
    return Math.min(Math.max((props.value / props.max) * 100, 0), 100);
});

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
    <div class="flex items-center justify-between p-5 bg-secondary-900/50 border border-secondary-800 rounded-xl relative overflow-hidden">
        <!-- Main Stat -->
        <div class="flex items-center gap-4">
            <!-- Gauge -->
            <div class="relative w-16 h-16 flex-shrink-0">
                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 80 80">
                    <circle
                        cx="40"
                        cy="40"
                        :r="radius"
                        fill="transparent"
                        stroke-width="8"
                        :class="bgClass"
                    />
                    <circle
                        cx="40"
                        cy="40"
                        :r="radius"
                        fill="transparent"
                        stroke-width="8"
                        stroke-linecap="round"
                        :class="colorClass"
                        :stroke-dasharray="circumference"
                        :stroke-dashoffset="dashOffset"
                        class="transition-all duration-1000 ease-out"
                    />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <component 
                        v-if="icon" 
                        :is="icon" 
                        class="w-5 h-5" 
                        :class="colorClass.replace('stroke-', 'text-')" 
                    />
                </div>
            </div>

            <!-- Text -->
            <div>
                <div class="text-secondary-400 text-sm font-medium">{{ title }}</div>
                <div class="text-2xl font-bold text-white tracking-tight">
                    {{ value }} <span class="text-sm text-secondary-500 font-normal">/ {{ max }}</span>
                </div>
                <div v-if="subtitle" class="text-xs text-secondary-500 font-medium mt-0.5">{{ subtitle }}</div>
            </div>
        </div>

        <!-- Breakdown List -->
        <div v-if="details && details.length" class="flex flex-col items-end gap-1.5 border-l border-secondary-800/50 pl-4 ml-2">
            <div v-for="(item, index) in details" :key="index" class="text-xs flex items-center gap-2">
                <span class="text-secondary-400">{{ item.label }}</span>
                <span class="font-bold" :class="item.color || 'text-white'">{{ item.value }}</span>
            </div>
        </div>
    </div>
</template>
