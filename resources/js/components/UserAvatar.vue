<script setup lang="ts">
import { computed } from 'vue';

export interface UserAvatarProps {
    name?: string;
    email?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    status?: 'online' | 'offline' | 'away';
    showStatus?: boolean;
}

export default defineComponent({
    name: 'UserAvatar',
    props: defineProps<UserAvatarProps>(),
    setup(props) {
        const initials = computed(() => {
            if (props.name) {
                return props.name
                    .split(' ')
                    .map(word => word.charAt(0).toUpperCase())
                    .join('')
                    .slice(0, 2);
            }
            if (props.email) {
                const [namePart] = props.email.split('@')[0];
                return namePart
                    .split(/[.-_]/)
                    .map(word => word.charAt(0).toUpperCase())
                    .join('')
                    .slice(0, 2);
            }
            return 'UN';
        });

        const statusConfig = {
            online: {
                bg: 'bg-success-500',
                ring: 'ring-success-500 ring-offset-2 ring-offset-secondary-950',
            },
            offline: {
                bg: 'bg-secondary-600',
                ring: 'ring-secondary-500 ring-offset-2 ring-offset-secondary-950',
            },
            away: {
                bg: 'bg-warning-500',
                ring: 'ring-warning-500 ring-offset-2 ring-offset-secondary-950',
            },
        };

        const status = props.status || 'offline';

        const config = statusConfig[status];

        const sizeClasses = {
            sm: 'w-6 h-6 text-xs',
            md: 'w-10 h-10 text-sm',
            lg: 'w-14 h-14 text-base',
            xl: 'w-20 h-20 text-lg',
        };

        const avatarClass = computed(() => {
            return `${sizeClasses[props.size || 'md']} ${config.bg} ${config.ring} text-white font-medium rounded-full flex items-center justify-center`;
        });

        return {
            initials,
            status,
            avatarClass,
        };
    },
});
</script>

<template>
    <div
        class="relative inline-block"
        :class="avatarClass"
    >
        <span class="font-bold">
            {{ initials }}
        </span>
        <div
            v-if="showStatus"
            class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-white border-2"
            :class="config.ring"
        >
            <div
                v-if="status === 'online'"
                class="w-2 h-2 bg-success-500 rounded-full animate-pulse"
            ></div>
            <div
                v-else-if="status === 'offline'"
                class="w-2 h-2 bg-secondary-600 rounded-full"
            ></div>
            <div
                v-else-if="status === 'away'"
                class="w-2 h-2 bg-warning-500 rounded-full"
            ></div>
        </div>
    </div>
</template>

<style scoped>
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s ease-in-out infinite;
}
</style>
