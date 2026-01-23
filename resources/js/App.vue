<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';
import { ref, onMounted } from 'vue';

const authStore = useAuthStore();
const isReady = ref(false);

onMounted(async () => {
    // Check if user is authenticated on app load
    // Wait for this to complete before showing the app
    await authStore.checkAuth();
    isReady.value = true;
});
</script>

<template>
    <div id="midgard-app" class="min-h-screen bg-secondary-950">
        <!-- Show loading state while checking auth -->
        <div v-if="!isReady" class="min-h-screen flex items-center justify-center">
            <div class="animate-pulse text-secondary-400">Loading...</div>
        </div>
        <!-- Only show routes after auth check -->
        <RouterView v-else />
    </div>
</template>
