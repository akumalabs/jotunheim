<script setup lang="ts">
import { ref } from 'vue';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import api from '@/lib/axios';

const appName = ref(import.meta.env.VITE_APP_NAME || 'Midgard');
const appUrl = ref(window.location.origin);

const loadingStates = ref({
    cache: false,
    route: false,
    config: false,
    optimize: false
});

const handleAction = async (action: 'cache' | 'route' | 'config' | 'optimize') => {
    loadingStates.value[action] = true;
    try {
        await api.post(`/admin/settings/${action === 'cache' ? 'clear-cache' : action === 'optimize' ? 'optimize' : `clear-${action}`}`);
        alert(`${action.charAt(0).toUpperCase() + action.slice(1)} cleared successfully`);
    } catch (error) {
        console.error(error);
        alert(`Failed to execute ${action} action`);
    } finally {
        loadingStates.value[action] = false;
    }
};
</script>

<template>
    <div class="p-6 space-y-6 animate-fade-in">
        <div>
            <h1 class="text-2xl font-bold text-white">Settings</h1>
            <p class="text-muted-foreground">Manage application configuration</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Application Info -->
            <Card>
                <CardHeader>
                    <h2 class="text-lg font-semibold text-white">Application Information</h2>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-muted-foreground">Application Name</label>
                        <p class="text-white mt-1">{{ appName }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-muted-foreground">Application URL</label>
                        <p class="text-white mt-1">{{ appUrl }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-muted-foreground">Environment</label>
                        <p class="text-white mt-1">Production</p>
                    </div>
                </CardContent>
            </Card>

            <!-- System Actions -->
            <Card>
                <CardHeader>
                    <h2 class="text-lg font-semibold text-white">System Actions</h2>
                </CardHeader>
                <CardContent class="space-y-3">
                    <Button 
                        variant="secondary" 
                        class="w-full"
                        :disabled="loadingStates.cache"
                        @click="handleAction('cache')"
                    >
                        {{ loadingStates.cache ? 'Clearing...' : 'Clear Application Cache' }}
                    </Button>
                    <Button 
                        variant="secondary" 
                        class="w-full"
                        :disabled="loadingStates.route"
                        @click="handleAction('route')"
                    >
                        {{ loadingStates.route ? 'Clearing...' : 'Clear Route Cache' }}
                    </Button>
                    <Button 
                        variant="secondary" 
                        class="w-full"
                        :disabled="loadingStates.config"
                        @click="handleAction('config')"
                    >
                        {{ loadingStates.config ? 'Clearing...' : 'Clear Config Cache' }}
                    </Button>
                    <Button 
                        variant="secondary" 
                        class="w-full"
                        :disabled="loadingStates.optimize"
                        @click="handleAction('optimize')"
                    >
                        {{ loadingStates.optimize ? 'Optimizing...' : 'Optimize Application' }}
                    </Button>
                </CardContent>
            </Card>
        </div>

        <!-- Additional Settings Sections -->
        <Card>
            <CardHeader>
                <h2 class="text-lg font-semibold text-white">Email Configuration</h2>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">
                    Email settings are configured via environment variables (.env file).
                    <br>Contact your system administrator to modify email configuration.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <h2 class="text-lg font-semibold text-white">Security</h2>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground mb-4">
                    Security settings such as session timeout, password requirements, and 2FA can be configured here.
                </p>
                <div class="text-sm text-yellow-500">
                    ⚠️ Advanced security features coming soon
                </div>
            </CardContent>
        </Card>
    </div>
</template>
