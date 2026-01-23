<script setup lang="ts">
import { ref } from 'vue';
import { useMutation } from '@tanstack/vue-query';
import { clientServerApi } from '@/api';
import {
    CircleStackIcon,
    ArrowPathIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    serverUuid: string;
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const storage = ref('');
const isoFile = ref('');
const error = ref('');

// Mount ISO
const mountMutation = useMutation({
    mutationFn: () => clientServerApi.mountIso(props.serverUuid, storage.value, isoFile.value),
    onSuccess: () => {
        emit('close');
        alert('ISO mounted successfully. You may need to reboot to access it.');
    },
    onError: (err: any) => {
        error.value = err.response?.data?.message || 'Failed to mount ISO';
    },
});

// Unmount ISO
const unmountMutation = useMutation({
    mutationFn: () => clientServerApi.unmountIso(props.serverUuid),
    onSuccess: () => {
        emit('close');
        alert('ISO unmounted successfully.');
    },
    onError: (err: any) => {
        error.value = err.response?.data?.message || 'Failed to unmount ISO';
    },
});
</script>

<template>
    <Teleport to="body">
        <div 
            v-if="show" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="emit('close')"
        >
            <div class="bg-secondary-900 rounded-xl shadow-2xl w-full max-w-md border border-secondary-700 animate-fade-in">
                <!-- Header -->
                <div class="flex items-center justify-between p-4 border-b border-secondary-700">
                    <h2 class="text-lg font-semibold text-white flex items-center">
                        <CircleStackIcon class="w-5 h-5 mr-2" />
                        ISO Management
                    </h2>
                    <button @click="emit('close')" class="text-secondary-400 hover:text-white">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-4 space-y-4">
                    <p class="text-secondary-400 text-sm">
                        Mount or unmount an ISO image to the virtual CD-ROM drive.
                    </p>

                    <div v-if="error" class="p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">
                        {{ error }}
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-secondary-300 mb-1">Storage</label>
                        <input
                            v-model="storage"
                            type="text"
                            class="input w-full"
                            placeholder="e.g., local"
                        />
                        <p class="text-xs text-secondary-500 mt-1">Storage name where ISOs are located</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-secondary-300 mb-1">ISO File</label>
                        <input
                            v-model="isoFile"
                            type="text"
                            class="input w-full"
                            placeholder="e.g., ubuntu-22.04.iso"
                        />
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="mountMutation.mutate()"
                            :disabled="mountMutation.isPending.value || !storage || !isoFile"
                            class="btn-primary flex-1"
                        >
                            <ArrowPathIcon v-if="mountMutation.isPending.value" class="w-4 h-4 animate-spin mr-2" />
                            Mount ISO
                        </button>
                        <button
                            @click="unmountMutation.mutate()"
                            :disabled="unmountMutation.isPending.value"
                            class="btn-secondary flex-1"
                        >
                            <ArrowPathIcon v-if="unmountMutation.isPending.value" class="w-4 h-4 animate-spin mr-2" />
                            Unmount
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
