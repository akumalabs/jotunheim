<script setup lang="ts">
import { 
    CpuChipIcon, 
    ServerStackIcon, 
    UserIcon,
    GlobeAltIcon,
    CodeBracketIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    activities: {
        type: Array,
        default: () => []
    }
});

const getIcon = (subjectType: string) => {
    switch (subjectType) {
        case 'Server': return ServerStackIcon;
        case 'Node': return CpuChipIcon;
        case 'User': return UserIcon;
        case 'Location': return GlobeAltIcon;
        default: return CodeBracketIcon;
    }
};

const statusColor = (status: string) => {
    switch (status) {
        case 'running': return 'text-blue-400 border-blue-500/30 bg-blue-500/10';
        case 'completed': return 'text-green-400 border-green-500/30 bg-green-500/10';
        case 'failed': return 'text-red-400 border-red-500/30 bg-red-500/10';
        default: return 'text-gray-400 border-gray-500/30 bg-gray-500/10';
    }
};
</script>

<template>
    <div class="bg-secondary-900/50 border border-secondary-800 rounded-xl p-4 h-full overflow-hidden flex flex-col">
        <div class="flex items-center justify-between mb-4 flex-shrink-0">
            <h3 class="text-secondary-300 font-medium text-sm">Recent Activity</h3>
            <div class="flex gap-2">
                <span class="flex items-center text-xs text-secondary-500"><span class="w-1.5 h-1.5 rounded-full bg-secondary-500 mr-1"></span> Log</span>
            </div>
        </div>

        <div class="space-y-3 overflow-y-auto pr-2 custom-scrollbar flex-1">
            <div v-if="!activities.length" class="text-center text-secondary-500 text-sm py-8">
                No recent activity.
            </div>
            <div v-for="activity in activities" :key="activity.id" 
                class="flex items-center gap-3 p-3 rounded-lg border border-secondary-800/50 bg-secondary-900/30 hover:bg-secondary-800/50 transition-colors cursor-default group"
            >
                
                <!-- Icon Box -->
                <div :class="['w-10 h-10 rounded-lg flex items-center justify-center border', statusColor(activity.status)]">
                    <component :is="getIcon(activity.subject_type)" class="w-5 h-5" />
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                        <span class="text-white font-medium text-sm truncate">{{ activity.description }}</span>
                        <span class="text-secondary-500 text-xs whitespace-nowrap">{{ activity.created_at }}</span>
                    </div>
                    <div class="flex justify-between items-center mt-0.5">
                        <span class="text-xs font-medium uppercase text-secondary-400">{{ activity.subject_type }} #{{ activity.subject_id }}</span>
                        <span class="text-secondary-600 text-xs capitalize">{{ activity.causer }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
