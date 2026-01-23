<script setup lang="ts">
import { ref, onMounted } from 'vue';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface ToastProps {
    type: ToastType;
    message: string;
    duration?: number;
    position?: 'top-right' | 'top-center' | 'bottom-right';
    showProgress?: boolean;
    progress?: number;
    onClose?: () => void;
}

export default defineComponent({
    name: 'ToastNotification',
    props: defineProps<ToastProps>(),
    emits: ['close'],
    setup(props, { emit }) {
        const visible = ref(false);
        const progress = ref(0);

        onMounted(() => {
            visible.value = true;

            if (props.showProgress && props.progress) {
                animateProgress(props.progress);
            }

            if (props.duration && props.duration > 0) {
                setTimeout(() => {
                    visible.value = false;
                    props.onClose?.();
                    emit('close');
                }, props.duration);
            } else if (props.duration === 0 || !props.duration) {
                setTimeout(() => {
                    visible.value = false;
                    props.onClose?.();
                    emit('close');
                }, 5000);
            }
        });

        const animateProgress = (target: number) => {
            const interval = setInterval(() => {
                if (progress.value < target) {
                    progress.value += 1;
                } else {
                    clearInterval(interval);
                }
            }, 50);
        };

        const getTypeIcon = () => {
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ',
            };
            return icons[props.type];
        };

        const getTypeClass = () => {
            const classes = {
                success: 'toast-success',
                error: 'toast-error',
                warning: 'toast-warning',
                info: 'toast-info',
            };
            return classes[props.type];
        };

        return {
            visible,
            progress,
            getTypeIcon,
            getTypeClass,
        };
    },
});
</script>

<template>
    <Transition
        name="toast-transition"
        enter-active-class="transition-all duration-300 ease-out"
        leave-active-class="transition-all duration-200 ease-in"
        enter-from-class="opacity-0 translate-y-[-20px]"
        enter-to-class="opacity-100 translate-y-[0px]"
        leave-from-class="opacity-100 translate-y-[0px]"
        leave-to-class="opacity-0 translate-y-[-20px]"
    >
        <div
            v-if="visible"
            class="fixed z-[100] glass-card rounded-xl shadow-2xl p-4 max-w-md"
            :class="[
                'top-4 right-4',
                'top-4 left-1/2',
                'bottom-4 right-4',
                'top-1/2 left-1/2 transform -translate-x-1/2'
            ][position || 'top-right']"
        >
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 text-2xl">
                    {{ getTypeIcon() }}
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium">
                        {{ message }}
                    </p>

                    <div
                        v-if="showProgress && progress > 0"
                        class="mt-3 bg-secondary-900/50 rounded-lg overflow-hidden"
                    >
                        <div
                            class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                            :style="{ width: `${progress}%` }"
                        ></div>
                    </div>
                </div>

                <button
                    @click="visible = false; props.onClose?.(); emit('close')"
                    class="flex-shrink-0 text-secondary-400 hover:text-white transition-colors duration-200 p-1 rounded-full hover:bg-secondary-800"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6L12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.toast-success {
    border-left: 4px solid theme('colors.success.500');
}

.toast-error {
    border-left: 4px solid theme('colors.danger.500');
}

.toast-warning {
    border-left: 4px solid theme('colors.warning.500');
}

.toast-info {
    border-left: 4px solid theme('colors.primary.500');
}

.toast-transition-enter-active,
.toast-transition-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.toast-transition-enter-from,
.toast-transition-leave-to {
    opacity: 0;
}
</style>
