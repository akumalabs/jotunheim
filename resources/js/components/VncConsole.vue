<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { clientServerApi } from '@/api';
import { XMarkIcon, ArrowsPointingOutIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    serverUuid: string;
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const iframeRef = ref<HTMLIFrameElement | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const consoleUrl = ref<string | null>(null);
const isFullscreen = ref(false);

const loadConsole = async () => {
    loading.value = true;
    error.value = null;

    try {
        const data = await clientServerApi.console(props.serverUuid);
        
        // Proxmox noVNC URL format
        // The VNC proxy returns ticket and port, we construct the noVNC URL
        const vncUrl = `${data.url}?console=kvm&vmid=100&node=pve&ticket=${encodeURIComponent(data.ticket)}`;
        consoleUrl.value = vncUrl;
    } catch (e: any) {
        error.value = e?.response?.data?.message || 'Failed to connect to console';
    } finally {
        loading.value = false;
    }
};

const toggleFullscreen = () => {
    if (!iframeRef.value) return;
    
    if (!document.fullscreenElement) {
        iframeRef.value.requestFullscreen();
        isFullscreen.value = true;
    } else {
        document.exitFullscreen();
        isFullscreen.value = false;
    }
};

watch(() => props.show, (newVal) => {
    if (newVal) {
        loadConsole();
    } else {
        consoleUrl.value = null;
    }
});

onMounted(() => {
    if (props.show) {
        loadConsole();
    }
});

onUnmounted(() => {
    consoleUrl.value = null;
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/80" @click="emit('close')"></div>
            
            <!-- Modal -->
            <div class="relative z-10 w-full max-w-6xl h-[80vh] bg-secondary-900 rounded-xl overflow-hidden shadow-2xl">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 bg-secondary-800 border-b border-secondary-700">
                    <h3 class="font-semibold text-white">Console</h3>
                    <div class="flex items-center gap-2">
                        <button 
                            @click="toggleFullscreen"
                            class="p-2 text-secondary-400 hover:text-white transition-colors"
                            title="Fullscreen"
                        >
                            <ArrowsPointingOutIcon class="w-5 h-5" />
                        </button>
                        <button 
                            @click="emit('close')"
                            class="p-2 text-secondary-400 hover:text-white transition-colors"
                        >
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="h-[calc(100%-3.5rem)] bg-black">
                    <!-- Loading -->
                    <div v-if="loading" class="h-full flex items-center justify-center">
                        <div class="text-center">
                            <div class="animate-spin w-8 h-8 border-2 border-primary-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                            <p class="text-secondary-400">Connecting to console...</p>
                        </div>
                    </div>
                    
                    <!-- Error -->
                    <div v-else-if="error" class="h-full flex items-center justify-center">
                        <div class="text-center text-danger-500">
                            <p class="mb-4">{{ error }}</p>
                            <button @click="loadConsole" class="btn-primary">
                                Retry
                            </button>
                        </div>
                    </div>
                    
                    <!-- Console iframe -->
                    <iframe 
                        v-else-if="consoleUrl"
                        ref="iframeRef"
                        :src="consoleUrl"
                        class="w-full h-full border-0"
                        allow="clipboard-read; clipboard-write"
                    ></iframe>
                    
                    <!-- Fallback instructions -->
                    <div v-else class="h-full flex items-center justify-center">
                        <div class="text-center text-secondary-400 max-w-md p-6">
                            <p class="mb-4">
                                The console connects directly to the Proxmox VNC server.
                            </p>
                            <p class="text-sm">
                                If the console doesn't load, ensure that:
                            </p>
                            <ul class="text-sm text-left mt-2 space-y-1 list-disc list-inside">
                                <li>The server is running</li>
                                <li>Your browser allows popups/iframes</li>
                                <li>The Proxmox node is accessible</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
