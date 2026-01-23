<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { ShieldCheckIcon } from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const isEnabled = ref(false);
const isSetupComplete = ref(false);

onMounted(() => {
    authStore.fetchUser();
    isEnabled.value = authStore.user?.two_factor_enabled || false;
});
</script>

<template>
    <div class="card animate-fade-in">
        <div class="card-header">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-primary-500/20 rounded-lg">
                    <ShieldCheckIcon class="w-6 h-6 text-primary-500" />
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-white">Two-Factor Authentication</h2>
                    <p class="text-sm text-secondary-400">
                        {{ isEnabled ? 'Your account is protected' : 'Enhance your account security' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div v-if="isEnabled" class="text-center py-8">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-success-500/10 flex items-center justify-center">
                    <ShieldCheckIcon class="w-10 h-10 text-success-500" />
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">2FA Enabled</h3>
                <p class="text-secondary-400">
                    Your account is now protected with two-factor authentication.
                </p>
            </div>

            <div v-else class="text-center py-8">
                <p class="text-secondary-300 mb-6">
                    Two-factor authentication adds an extra layer of security to your account.
                    In addition to your password, you'll need to enter a code from your authenticator app.
                </p>
                <button class="btn-primary">
                    Enable Two-Factor Authentication
                </button>
            </div>
        </div>
    </div>
</template>
