<script setup lang="ts">
import { ref, computed } from 'vue';
import { ShieldCheckIcon, LockClosedIcon, DocumentDuplicateIcon } from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();

const isEnabled = ref(false);
const isSetupComplete = ref(false);
const qrCode = ref('');
const recoveryCodes = ref<string[]>([]);
const setupCode = ref('');
const verifyCode = ref('');
const password = ref('');

const hasRecoveryCodes = computed(() => recoveryCodes.value.length > 0);

const handleGenerate = async () => {
    try {
        const response = await fetch('/api/v1/auth/2fa/setup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
        });

        if (response.ok) {
            const result = await response.json();
            qrCode.value = result.qr_code || '';
            recoveryCodes.value = result.recovery_codes || [];
            isSetupComplete.value = true;
        }
    } catch (error) {
        console.error('Failed to setup 2FA:', error);
    }
};

const handleVerify = async () => {
    try {
        const response = await fetch('/api/v1/auth/2fa/verify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                code: verifyCode.value,
            }),
        });

        if (response.ok) {
            isEnabled.value = true;
            await authStore.fetchUser();
        }
    } catch (error) {
        console.error('Verification failed:', error);
    }
};

const copyCode = (code: string) => {
    navigator.clipboard.writeText(code);
};
</script>

<template>
    <div class="max-w-3xl mx-auto px-6">
        <h2 class="text-3xl font-bold text-white mb-6">Two-Factor Authentication</h2>

        <div class="card">
            <div class="card-header">
                <div class="flex items-center gap-3">
                    <ShieldCheckIcon class="w-6 h-6 text-primary-500" />
                    <span class="text-lg font-semibold text-white">Setup 2FA</span>
                </div>
            </div>

            <div class="card-body">
                <div v-if="isEnabled" class="text-center py-8">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-success-500/20 flex items-center justify-center">
                        <ShieldCheckIcon class="w-10 h-10 text-success-500" />
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">2FA Enabled</h3>
                    <p class="text-secondary-400">
                        Your account is now protected with two-factor authentication.
                    </p>
                </div>

                <div v-else-if="isSetupComplete" class="space-y-6">
                    <div>
                        <h4 class="text-white font-medium mb-2">Step 2: Scan QR Code</h4>
                        <p class="text-secondary-400 text-sm mb-4">
                            Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
                        </p>
                        <div v-if="qrCode" class="bg-white p-4 rounded-lg inline-block">
                            <div class="text-center text-black">{{ qrCode }}</div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-white font-medium mb-2">Step 3: Verify</h4>
                        <p class="text-secondary-400 text-sm mb-4">
                            Enter the 6-digit code from your authenticator app
                        </p>
                        <div class="space-y-4">
                            <div>
                                <label class="label">Verification Code</label>
                                <input
                                    v-model="verifyCode"
                                    type="text"
                                    maxlength="6"
                                    class="input"
                                    placeholder="123456"
                                />
                            </div>
                            <button @click="handleVerify" class="btn-primary">
                                Verify & Enable
                            </button>
                        </div>
                    </div>

                    <div v-if="hasRecoveryCodes" class="border-t border-secondary-800 pt-6">
                        <div class="flex items-center gap-2 mb-4">
                            <LockClosedIcon class="w-5 h-5 text-warning-500" />
                            <h4 class="text-white font-medium">Recovery Codes</h4>
                        </div>
                        <p class="text-secondary-400 text-sm mb-4">
                            Save these codes in a secure location. Each code can be used once.
                        </p>
                        <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto">
                            <div
                                v-for="(code, index) in recoveryCodes"
                                :key="index"
                                class="bg-secondary-800 rounded p-2 flex items-center justify-between"
                            >
                                <span class="font-mono text-sm text-white">{{ code }}</span>
                                <button
                                    @click="copyCode(code)"
                                    class="text-secondary-400 hover:text-white"
                                    title="Copy"
                                >
                                    <DocumentDuplicateIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="space-y-6">
                    <div>
                        <h4 class="text-white font-medium mb-2">Step 1: Generate 2FA</h4>
                        <p class="text-secondary-400 text-sm mb-4">
                            Click the button below to generate your two-factor authentication secret.
                        </p>
                        <button @click="handleGenerate" class="btn-primary">
                            Generate QR Code
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
</style>

            <VerifyForm
                :open="props.open"
                :loading="data.loading"
                @handleSubmit="handleVerify"
            >
                <template v-if="props.open">
                    <div class="section-header">
                        <span class="text-lg font-semibold text-white">Step 2: Enter Verification Code</span>
                    </div>
                    <div class="section-description">
                        <p class="text-sm text-secondary-300 mb-4">
                            Enter the 6-digit code displayed in your authenticator app.
                        </p>
                    </div>
                    <div class="input-group">
                        <input
                            type="text"
                            v-model="values.verify"
                            maxlength="6"
                            placeholder="123456"
                            class="input-field"
                            :disabled="data.loading"
                        />
                    </div>

                    <div v-if="errors.password" class="text-danger-500 mt-2 mb-4">
                        {{ errors.password }}
                    </div>

                    <div class="form-actions">
                        <button
                            type="button"
                            @click="handleVerify"
                            :disabled="data.loading || !values.verify || !values.password || !values.recovery_code"
                            class="btn-primary"
                        >
                            <span v-if="data.loading">Verifying...</span>
                            <span v-else>Verify</span>
                        </button>
                    </div>
            </template>

            <SetupSuccess
                :open="props.open"
                @onSetup="onSetup"
            >
                <div class="max-w-2xl mx-auto px-6 text-center">
                    <div class="info-icon">
                        <ShieldCheckIcon class="w-12 h-12 text-success-500" />
                    </div>
                    <h3 class="text-2xl font-bold text-white mt-4">Two-Factor Authentication Enabled</h3>
                    <p class="text-lg text-secondary-200 mt-4">
                        Your account is now protected with two-factor authentication.
                    </p>
                    <p class="text-sm text-secondary-300 mt-2">
                        Make sure to save your recovery codes in a secure location.
                    </p>
                    <p class="text-sm text-secondary-300 mt-4">
                        When logging in, you will be asked for your 6-digit code.
                    </p>
                </div>
            </template>

            <RecoveryCodesDisplay
                v-if="hasRecoveryCodes"
            >
                <div class="section-header">
                    <span class="text-lg font-semibold text-white">Recovery Codes</span>
                    <span class="info-icon">
                        <LockOpenIcon class="w-5 h-5 text-secondary-400" />
                    </span>
                </div>
                <div class="section-description">
                    <p class="text-sm text-secondary-300 mb-4">
                        Save these codes in a secure location for account recovery.
                        Each code can be used once.
                    </p>
                </div>
                <div class="recovery-codes-container">
                    <div
                        v-for="(code, index) in recoveryCodes"
                        :key="index"
                        class="recovery-code"
                    >
                        <div class="code-text font-mono">{{ code }}</div>
                        <button
                            @click="navigator.clipboard.writeText(code)"
                            class="copy-btn"
                        >
                            <CopyIcon />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
