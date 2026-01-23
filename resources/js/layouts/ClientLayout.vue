<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import {
    HomeIcon,
    ServerIcon,
    Cog6ToothIcon,
    UserIcon,
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const sidebarOpen = ref(false);
const isMobile = ref(false);

const navigation = [
    {
        name: 'client.dashboard',
        label: 'Dashboard',
        path: '/client/dashboard',
        icon: HomeIcon,
        routeName: 'client.dashboard'
    },
    {
        name: 'client.servers',
        label: 'My Servers',
        path: '/client/servers',
        icon: ServerIcon,
        routeName: 'client.servers'
    },
    {
        name: 'client.backups',
        label: 'Backups',
        path: '/client/backups',
        icon: Cog6ToothIcon,
        routeName: 'client.backups'
    },
    {
        name: 'client.settings',
        label: 'Settings',
        path: '/client/settings',
        icon: Cog6ToothIcon,
        routeName: 'client.settings'
    },
];

const isActiveRoute = (routeName: string) => {
    return route.name === routeName;
};

const handleLogout = async () => {
    try {
        await authStore.logout();
        router.push({ name: 'login' });
    } catch (error) {
        console.error('Logout failed:', error);
    }
};

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

onMounted(() => {
    const handleResize = () => {
        isMobile.value = window.innerWidth < 1024;
    };
    window.addEventListener('resize', handleResize);
    handleResize();
});
</script>

<template>
    <div class="min-h-screen bg-secondary-950 flex">
        <!-- Mobile menu button -->
        <button
            v-if="isMobile"
            @click="toggleSidebar"
            class="fixed top-4 right-4 z-50 p-3 bg-primary-600 rounded-lg text-white lg:hidden hover:bg-primary-500 transition-colors duration-200 shadow-2xl"
        >
            <Bars3Icon v-if="!sidebarOpen" class="w-6 h-6" />
            <XMarkIcon v-else class="w-6 h-6" />
        </button>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 bg-secondary-900/95 backdrop-blur-xl border-r border-secondary-800 w-64 transition-transform duration-300 lg:static lg:block"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                !sidebarOpen && isMobile ? 'opacity-0 pointer-events-none' : 'opacity-100'
            ]"
        >
            <div class="h-full flex flex-col">
                <!-- Logo -->
                <div class="px-6 py-6 border-b border-secondary-800">
                    <RouterLink to="/client/dashboard" class="flex items-center gap-3">
                        <div class="w-10 h-10">
                            <img src="/logo.svg" alt="Midgard Logo" class="w-full h-full" />
                        </div>
                        <span class="text-xl font-bold text-white">Midgard</span>
                    </RouterLink>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-1">
                    <RouterLink
                        v-for="item in navigation"
                        :key="item.name"
                        :to="item.path"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition-all duration-200 nav-link hover:bg-secondary-800/50 text-white"
                        :class="isActiveRoute(item.routeName) ? 'nav-link-active' : ''"
                    >
                        <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                        <span class="text-sm font-medium">{{ item.label }}</span>
                    </RouterLink>
                </nav>

                <!-- User section -->
                <div class="border-t border-secondary-800 p-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-primary-500 flex items-center justify-center text-white">
                            <UserIcon class="w-6 h-6" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">
                                {{ authStore.user?.name || 'User' }}
                            </p>
                            <p class="text-xs text-secondary-400 truncate">
                                {{ authStore.user?.email || 'user@midgard.local' }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="handleLogout"
                        class="flex items-center gap-2 w-full px-4 py-2.5 text-sm font-medium text-secondary-300 hover:text-danger-400 hover:bg-secondary-800/50 rounded-lg transition-all duration-200"
                    >
                        <ArrowRightOnRectangleIcon class="w-5 h-5" />
                        <span>Sign Out</span>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Page content -->
        <main class="flex-1 min-h-screen bg-gradient-bg">
            <RouterView />
        </main>
    </div>
</template>

<style scoped>
.nav-link:hover {
    background: linear-gradient(135deg, oklch(0.60 0.10 280 0.5 / 0.01) 100%);
}

.nav-link-active {
    background: linear-gradient(135deg, oklch(0.98 0.08 290 0.5 / 0.01) 100%);
    border-left: 4px solid oklch(0.65 0.20 290);
}

.glass-card {
    background: rgba(30 41 59, 0.6);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(148 163 184, 0.15);
}

.gradient-bg {
    background: linear-gradient(135deg, oklch(0.98 0.08 290 0.5 / 0.01) 100%);
}

@media (prefers-color-scheme: dark) {
    .gradient-bg {
        background: linear-gradient(135deg, oklch(0.98 0.08 290 0.5 / 0.01) 100%);
    }
}
</style>
