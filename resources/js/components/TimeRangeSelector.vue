<template>
    <div class="flex gap-2">
        <button
            v-for="range in timeRanges"
            :key="range.label"
            @click="$emit('update:modelValue', range.label)"
            :class="[
                'px-3 py-1.5 text-sm font-medium rounded-lg transition-all duration-200',
                modelValue === range.label
                    ? 'bg-primary-600 text-white'
                    : 'bg-secondary-800 text-secondary-300 hover:bg-secondary-700'
            ]"
        >
            {{ range.label }}
        </button>
    </div>
</template>

<script setup lang="ts">
const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits(['update:modelValue']);

// Map display labels to Proxmox API timeframe values
const timeframeMap: Record<string, string> = {
    '30m': 'hour',
    '1h': 'hour',
    '12h': 'day',
    '1d': 'day',
    '1wk': 'week',
};

const timeRanges = [
    { label: '30m' },
    { label: '1h' },
    { label: '12h' },
    { label: '1d' },
    { label: '1wk' },
];

// Helper to get API value from label
defineExpose({ getApiValue: (label: string) => timeframeMap[label] || 'hour' });
</script>
