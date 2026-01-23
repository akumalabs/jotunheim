<script setup lang="ts">
import { ref, computed } from 'vue';
import { Bars3Icon, UserIcon, ServerIcon, Cog6ToothIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';

interface MenuItem {
    id: string;
    label: string;
    icon: any;
    badge?: string | number;
    path: string;
    children?: MenuItem[];
    disabled?: boolean;
}

interface MenuProps {
    size?: 'sm' | 'md' | 'lg';
    variant?: 'sidebar' | 'topbar' | 'dropdown';
}

const props = withDefaults(defineProps<MenuProps>(), {
    size: 'md',
    variant: 'sidebar',
});

const emit = defineEmits<{
    (e: 'navigate', path: string): void;
}>();

const activeDropdown = ref<string | null>(null);

const menuItems = computed(() => [
    {
        id: 'dashboard',
        label: 'Dashboard',
        icon: Bars3Icon,
        path: '/client/dashboard',
        children: props.variant === 'sidebar' ? [] : [
            {
                id: 'servers',
                label: 'My Servers',
                icon: ServerIcon,
                path: '/client/servers',
            },
            {
                id: 'backups',
                label: 'Backups',
                icon: Cog6ToothIcon,
                path: '/client/backups',
            },
            {
                id: 'settings',
                label: 'Settings',
                icon: ShieldCheckIcon,
                path: '/client/settings',
            },
        ],
    },
    {
        id: 'admin',
        label: 'Admin',
        icon: UserIcon,
        path: '/admin',
        children: [
            {
                id: 'admin-servers',
                label: 'Servers',
                path: '/admin/servers',
            },
            {
                id: 'admin-users',
                label: 'Users',
                path: '/admin/users',
            },
            {
                id: 'admin-nodes',
                label: 'Nodes',
                path: '/admin/nodes',
            },
            {
                id: 'admin-locations',
                label: 'Locations',
                path: '/admin/locations',
            },
            {
                id: 'admin-activity',
                label: 'Activity Logs',
                path: '/admin/activity-logs',
            },
        ],
    },
    {
        id: 'billing',
        label: 'Billing',
        icon: Cog6ToothIcon,
        path: '/billing',
    },
    {
        id: 'api',
        label: 'API Docs',
        icon: ServerIcon,
        path: '/docs',
    },
]);

const handleClick = (item: MenuItem) => {
    if (item.disabled) return;
    emit('navigate', item.path);
};

const toggleDropdown = (id: string) => {
    activeDropdown.value = activeDropdown.value === id ? null : id;
};
</script>

<template>
    <nav :class="['flex items-center gap-2', { 'p-4': variant === 'sidebar' }]">
        <div v-for="item in menuItems" :key="item.id" class="relative group">
            <button
                @click="handleClick(item)"
                :disabled="item.disabled"
                class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all duration-200"
                :class="[
                    'text-secondary-300 hover:text-white',
                    'hover:bg-secondary-800',
                    item.disabled && 'opacity-50 cursor-not-allowed'
                ]"
            >
                <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                <span class="text-sm font-medium">{{ item.label }}</span>
                <span
                    v-if="item.badge"
                    class="ml-2 px-2.5 py-0.5 rounded-full bg-primary-500 text-white text-xs font-bold"
                >
                    {{ item.badge }}
                </span>
            </button>

            <div
                v-if="item.children && item.children.length > 0"
                class="absolute left-full top-12 min-w-48 glass-card rounded-lg shadow-2xl p-2"
                @click.stop
            >
                <div
                    v-for="child in item.children"
                    :key="child.id"
                    class="cursor-pointer"
                    @click="handleClick(child)"
                >
                    <component :is="child.icon" class="w-4 h-4 flex-shrink-0" />
                    <span class="text-sm text-secondary-300">{{ child.label }}</span>
                </div>
            </div>

            <div
                v-if="item.badge === 'notification'"
                class="absolute -top-1 -right-1"
            >
                <div class="w-2 h-2 rounded-full bg-danger-500 animate-pulse">
                    <span class="text-white text-xs">!</span>
                </div>
            </div>
        </div>
    </nav>
</template>

<style scoped>
.glass-card {
    background: rgba(30 41 59, 0.7);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(148 163 184, 0.15);
}
</style>
