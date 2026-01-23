<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import {
    HomeIcon,
    ServerStackIcon,
    UsersIcon,
    MapPinIcon,
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    XMarkIcon,
    BellIcon,
    Cog6ToothIcon,
    CubeIcon,
    QueueListIcon,
    DocumentDuplicateIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const sidebarOpen = ref(false);
const notificationsOpen = ref(false);

const isActiveRoute = (routeName: string) => {
    return route.name === routeName;
};

const navigation = [
    {
        name: 'admin.dashboard',
        href: '/admin',
        icon: HomeIcon,
        routeName: 'admin.dashboard',
        label: 'Dashboard',
    },
    {
        name: 'admin.nodes',
        href: '/admin/nodes',
        icon: CubeIcon,
        routeName: 'admin.nodes',
        label: 'Nodes',
    },
    {
        name: 'admin.servers',
        href: '/admin/servers',
        icon: ServerStackIcon,
        routeName: 'admin.servers',
        label: 'Servers',
    },
    {
        name: 'admin.address-pools',
        href: '/admin/address-pools',
        icon: QueueListIcon,
        routeName: 'admin.address-pools',
        label: 'IP Pools',
    },
    {
        name: 'admin.users',
        href: '/admin/users',
        icon: UsersIcon,
        routeName: 'admin.users',
        label: 'Users',
    },
    {
        name: 'admin.locations',
        href: '/admin/locations',
        icon: MapPinIcon,
        routeName: 'admin.locations',
        label: 'Locations',
    },
    {
        name: 'admin.templates',
        href: '/admin/templates',
        icon: DocumentDuplicateIcon,
        routeName: 'admin.templates',
        label: 'Templates',
    },
    {
        name: 'admin.activity',
        href: '/admin/activity-logs',
        icon: BellIcon,
        routeName: 'admin.activity',
        label: 'Activity Logs',
    },
    {
        name: 'admin.settings',
        href: '/admin/settings',
        icon: Cog6ToothIcon,
        routeName: 'admin.settings',
        label: 'Settings',
    },
];

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const toggleNotifications = () => {
    notificationsOpen.value = !notificationsOpen.value;
};

const handleLogout = async () => {
    try {
        await authStore.logout();
        router.push({ name: 'login' });
    } catch (error) {
        console.error('Logout failed:', error);
    }
};

onMounted(() => {
    const handleResize = () => {
        sidebarOpen.value = window.innerWidth < 1024;
    };
    window.addEventListener('resize', handleResize);
    handleResize();
});
</script>

<template>
    <div class="min-h-screen bg-secondary-950 flex">
        <!-- Mobile menu button -->
        <button
            @click="toggleSidebar"
            class="fixed top-4 left-4 z-50 p-3 bg-primary-600 rounded-lg text-white lg:hidden hover:bg-primary-500 transition-colors duration-200 shadow-2xl"
        >
            <Bars3Icon v-if="!sidebarOpen" class="w-6 h-6" />
            <XMarkIcon v-else class="w-6 h-6" />
        </button>

        <!-- Mobile sidebar backdrop -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/80 lg:hidden"
            @click="toggleSidebar"
        ></div>

        <!-- Mobile sidebar -->
        <aside
            v-if="sidebarOpen"
            class="lg:hidden fixed inset-y-0 left-0 z-50 flex flex-col w-64 bg-secondary-900/95 backdrop-blur-xl border-r border-secondary-800"
        >
            <!-- Logo section -->
            <div class="px-6 py-6 border-b border-secondary-800">
                <RouterLink
                    to="/admin"
                    @click="toggleSidebar"
                    class="flex items-center gap-3 group"
                >
                    <div
                        class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-600/40 via-primary-500/80 text-white flex items-center justify-center font-bold transition-all duration-200"
                    >
                        <span class="text-2xl">M</span>
                    </div>
                    <span class="text-xl font-bold text-white">Midgard</span>
                </RouterLink>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <RouterLink
                    v-for="item in navigation"
                    :key="item.name"
                    :to="item.href"
                    @click="toggleSidebar"
                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition-all duration-200 nav-link hover:bg-secondary-800/50 text-white"
                    :class="[
                        isActiveRoute(item.routeName),
                        'nav-link-active'
                    ]"
                >
                    <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                    <span class="text-sm font-medium">{{ item.label }}</span>
                </RouterLink>
            </nav>

            <!-- User section -->
            <div class="border-t border-secondary-800 p-4">
                <button
                    @click="handleLogout"
                    class="flex items-center gap-2 w-full px-4 py-2.5 text-sm font-medium text-secondary-300 hover:text-danger-400 hover:bg-secondary-800/50 rounded-lg transition-all duration-200"
                >
                    <ArrowRightOnRectangleIcon class="w-5 h-5" />
                    <span>Sign Out</span>
                </button>
            </div>
        </aside>

        <!-- Desktop sidebar -->
        <aside
            class="hidden lg:flex flex-col w-64 h-full bg-secondary-900/50 border-r border-secondary-800"
            :class="[
                'transition-transform duration-300',
                sidebarOpen ? 'translate-x-0' : 'translate-x-0'
            ]"
        >
            <!-- Logo section -->
            <div class="px-6 py-6 border-b border-secondary-800">
                <RouterLink
                    to="/admin"
                    class="flex items-center gap-3 group"
                >
                    <div class="w-10 h-10">
                        <img src="/logo.svg" alt="Midgard" class="w-full h-full" />
                    </div>
                    <span class="text-xl font-bold text-white">Midgard</span>
                </RouterLink>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <RouterLink
                    v-for="item in navigation"
                    :key="item.name"
                    :to="item.href"
                    class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition-all duration-200 nav-link hover:bg-secondary-800/50 text-white"
                    :class="[
                        isActiveRoute(item.routeName),
                        'nav-link-active'
                    ]"
                >
                    <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                    <span class="text-sm font-medium">{{ item.label }}</span>
                </RouterLink>
            </nav>

            <!-- User section -->
            <div class="border-t border-secondary-800 p-4">
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-3 px-2">
                        <div class="w-8 h-8 rounded-full bg-primary-500/20 flex items-center justify-center text-primary-200 font-bold">
                            {{ authStore.user?.name?.charAt(0)?.toUpperCase() || 'A' }}
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-white">{{ authStore.user?.name || 'Admin User' }}</span>
                            <span class="text-xs text-secondary-400">{{ authStore.user?.email || 'admin@midgard.local' }}</span>
                        </div>
                    </div>

                    <button
                        @click="handleLogout"
                        class="flex items-center gap-3 px-2 py-2 text-sm font-medium text-secondary-400 hover:text-danger-400 hover:bg-white/5 rounded-lg transition-all duration-200"
                    >
                        <ArrowRightOnRectangleIcon class="w-5 h-5" />
                        <span>Sign Out</span>
                    </button>
                </div>
            </div>

        </aside>

        <!-- Notifications center -->
        <Teleport to="body">
            <div
                v-if="notificationsOpen"
                class="fixed inset-0 z-50 flex items-start justify-center pt-4"
                @click="toggleNotifications"
            >
                <div class="glass-card rounded-xl shadow-2xl p-4 max-w-md mt-16" @click.stop>
                    <h3 class="text-lg font-semibold text-white mb-4">Notifications</h3>
                    <div class="text-sm text-secondary-300 mb-4">
                        <p>No new notifications</p>
                    </div>
                    <button
                        @click="toggleNotifications"
                        class="absolute -top-2 -right-2 text-secondary-400 hover:text-white transition-colors duration-200 p-1"
                    >
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </Teleport>

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
