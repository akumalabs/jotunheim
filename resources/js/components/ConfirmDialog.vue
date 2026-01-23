<script setup lang="ts">
import { ref } from 'vue';

export interface ConfirmDialogProps {
    open: boolean;
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    type?: 'danger' | 'warning' | 'info';
    onConfirm: () => void;
    onCancel: () => void;
}

export default defineComponent({
    name: 'ConfirmDialog',
    props: defineProps<ConfirmDialogProps>(),
    emits: ['confirm', 'cancel'],
    setup(props, { emit }) {
        const handleConfirm = () => {
            emit('confirm');
        };

        const handleCancel = () => {
            emit('cancel');
        };

        const typeColors = {
            danger: 'border-danger-500/50 text-danger-100',
            warning: 'border-warning-500/50 text-warning-100',
            info: 'border-primary-500/50 text-primary-100',
        };

        return {
            props,
            handleConfirm,
            handleCancel,
            typeColors,
        };
    },
});
</script>

<template>
    <Teleport to="body">
        <Transition
            name="confirm-dialog"
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-200 ease-in"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[100] flex items-center justify-center p-6"
            >
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="handleCancel"
                ></div>

                <div
                    class="relative bg-secondary-900/95 border border-secondary-700 rounded-xl shadow-2xl max-w-md"
                >
                    <div class="px-6 py-4">
                        <h3 class="text-lg font-semibold text-white mb-2">
                            {{ title }}
                        </h3>
                        <p class="text-sm text-secondary-300 mb-4">
                            {{ message }}
                        </p>
                        <div
                            class="flex gap-3"
                        >
                            <button
                                @click="handleConfirm"
                                :class="['btn-primary flex-1', typeColors[type]]"
                            >
                                <span class="font-medium">
                                    {{ confirmText || 'Yes, confirm' }}
                                </span>
                            </button>

                            <button
                                @click="handleCancel"
                                class="btn-secondary flex-1"
                            >
                                <span class="font-medium">
                                    {{ cancelText || 'Cancel' }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.btn-primary {
    @apply inline-flex items-center justify-center px-6 py-2 rounded-lg font-medium transition-all duration-200 bg-primary-600 text-white hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-secondary-950;
}

.btn-danger {
    @apply inline-flex items-center justify-center px-6 py-2 rounded-lg font-medium transition-all duration-200 bg-danger-600 text-white hover:bg-danger-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-secondary-950;
}

.btn-secondary {
    @apply inline-flex items-center justify-center px-6 py-2 rounded-lg font-medium transition-all duration-200 bg-secondary-800 text-white hover:bg-secondary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-secondary-950 border border-secondary-700;
}
</style>
