<script setup lang="ts">
import { ref, watch } from 'vue';

interface BaseModalProps {
    open: boolean;
    title: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    showClose?: boolean;
    persistent?: boolean;
}

const props = withDefaults(defineProps<BaseModalProps>(), {
    size: 'md',
    showClose: true,
    persistent: false,
});

const emit = defineEmits<{
    close: [];
}>();

const isAnimating = ref(false);
const modalContent = ref<HTMLElement | null>(null);

const handleBackdropClick = () => {
    if (!props.persistent) {
        emit('close');
    }
};

const handleEscapeKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && !props.persistent) {
        emit('close');
    }
};

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('keydown', handleEscapeKey);
        document.body.style.overflow = 'hidden';
    } else {
        document.removeEventListener('keydown', handleEscapeKey);
        document.body.style.overflow = '';
    }
});

const getSizeClass = () => {
    const sizes = {
        sm: 'max-w-md',
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-5xl',
    };
    return sizes[props.size || 'md'];
};
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="handleBackdropClick"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

                <!-- Modal Content -->
                <div
                    ref="modalContent"
                    :class="[
                        'relative w-full glass-card border border-secondary-700/50 rounded-2xl shadow-2xl',
                        'transform transition-all duration-300',
                        getSizeClass(),
                    ]"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between p-6 border-b border-secondary-700/50">
                        <h3 class="text-xl font-semibold text-white">{{ title }}</h3>
                        <button
                            v-if="showClose"
                            @click="emit('close')"
                            class="text-secondary-400 hover:text-white transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6">
                        <slot />
                    </div>

                    <!-- Footer (optional) -->
                    <div v-if="$slots.footer" class="p-6 border-t border-secondary-700/50">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .glass-card,
.modal-leave-active .glass-card {
    transition: transform 0.3s ease;
}

.modal-enter-from .glass-card,
.modal-leave-to .glass-card {
    transform: scale(0.95);
}
</style>
