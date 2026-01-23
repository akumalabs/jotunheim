<template>
    <div class="glass-card border border-secondary-700/50 rounded-xl p-6 hover:border-primary-500/30 transition-all">
        <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-primary-500/10 rounded-lg">
                    <component :is="icon" class="w-6 h-6 text-primary-400" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
                    <p class="text-sm text-secondary-400">{{ description }}</p>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-white">{{ currentValue }}</span>
                <span class="text-secondary-400">{{ unit }}</span>
            </div>
            <p class="text-xs text-secondary-500 mt-1">Current allocation</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <button
                v-for="option in upgradeOptions"
                :key="option"
                @click="$emit('upgrade', option)"
                :disabled="option <= currentValue || disabled"
                :class="[
                    'px-4 py-2 rounded-lg font-medium transition-all',
                    option <= currentValue
                        ? 'bg-secondary-800 text-secondary-600 cursor-not-allowed'
                        : 'bg-primary-500/10 text-primary-400 hover:bg-primary-500/20 border border-primary-500/30 hover:border-primary-500/50',
                    disabled && 'opacity-50 cursor-not-allowed'
                ]"
            >
                {{ option }} {{ unit }}
            </button>
        </div>

        <div v-if="note" class="mt-4 p-3 bg-warning-500/10 border border-warning-500/20 rounded-lg">
            <p class="text-xs text-warning-400">{{ note }}</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { Component } from 'vue';

interface UpgradeCardProps {
    title: string;
    description: string;
    icon: Component;
    currentValue: number;
    unit: string;
    upgradeOptions: number[];
    disabled?: boolean;
    note?: string;
}

defineProps<UpgradeCardProps>();

defineEmits<{
    upgrade: [value: number];
}>();
</script>
