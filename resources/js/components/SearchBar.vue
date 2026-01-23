<script setup lang="ts">
import { ref, watch } from 'vue';

export interface SearchBarProps {
    modelValue?: string;
    placeholder?: string;
    disabled?: boolean;
    size?: 'sm' | 'md' | 'lg';
}

const emit = defineEmits<{
    'update:modelValue': [value: string],
    'search': [value: string],
}>();

export default defineComponent({
    name: 'SearchBar',
    props: defineProps<SearchBarProps>(),
    emits,
    setup(props, { emit }) {
        const searchTerm = ref(props.modelValue || '');

        watch(() => searchTerm.value, (newVal) => {
            emit('update:modelValue', newVal);
        });

        const handleInput = (event: Event) => {
            const target = event.target as HTMLInputElement;
            searchTerm.value = target.value;
        };

        const handleSearch = () => {
            if (searchTerm.value.trim()) {
                emit('search', searchTerm.value);
            }
        };

        const clearSearch = () => {
            searchTerm.value = '';
            emit('search', '');
        };

        const sizeClasses = {
            sm: 'h-9 w-48 text-sm',
            md: 'h-10 w-64 text-base',
            lg: 'h-12 w-80 text-lg',
        };

        return {
            searchTerm,
            handleInput,
            handleSearch,
            clearSearch,
            sizeClasses: sizeClasses[props.size || 'md'],
        };
    },
});
</script>

<template>
    <div class="relative" :class="sizeClasses">
        <input
            type="text"
            :value="searchTerm"
            @input="handleInput"
            :placeholder="placeholder || 'Search...'"
            :disabled="disabled"
            class="w-full bg-secondary-900 border border-secondary-700 rounded-lg text-white placeholder-secondary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-200"
        />

        <button
            v-if="searchTerm"
            @click="clearSearch"
            class="absolute right-2 top-1/2 text-secondary-400 hover:text-white transition-colors duration-200 p-1"
            title="Clear search"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6L12 12" />
            </svg>
        </button>

        <button
            v-if="!disabled"
            @click="handleSearch"
            class="absolute right-8 top-1/2 text-secondary-400 hover:text-white transition-colors duration-200 p-1"
            title="Search"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6 6h-3" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6h-3" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9l3 3h-3" />
            </svg>
        </button>
    </div>
</template>

<style scoped>
input::placeholder {
    color: theme('colors.secondary.500');
}
</style>
