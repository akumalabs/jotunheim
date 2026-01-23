<template>
    <slot v-if="hasPermission" />
    <slot v-else name="fallback" />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps<{
    permission: string | string[];
    requireAll?: boolean;
}>();

const { hasPermission: checkPermission, hasAllPermissions, hasAnyPermission } = usePermissions();

const hasPermission = computed(() => {
    if (Array.isArray(props.permission)) {
        return props.requireAll 
            ? hasAllPermissions(props.permission)
            : hasAnyPermission(props.permission);
    }
    return checkPermission(props.permission);
});
</script>
