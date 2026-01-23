import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

export function usePermissions() {
    const auth = useAuthStore();

    /**
     * Check if user has a specific permission
     */
    const hasPermission = (permission: string): boolean => {
        if (!auth.user) return false;
        return auth.user.permissions?.includes(permission) ?? false;
    };

    /**
     * Check if user has any of the given permissions
     */
    const hasAnyPermission = (permissions: string[]): boolean => {
        return permissions.some(p => hasPermission(p));
    };

    /**
     * Check if user has all of the given permissions
     */
    const hasAllPermissions = (permissions: string[]): boolean => {
        return permissions.every(p => hasPermission(p));
    };

    /**
     * Check if user has a specific role
     */
    const hasRole = (role: string): boolean => {
        if (!auth.user) return false;
        return auth.user.roles?.includes(role) ?? false;
    };

    /**
     * Check if user has any of the given roles
     */
    const hasAnyRole = (roles: string[]): boolean => {
        return roles.some(r => hasRole(r));
    };

    /**
     * Check if user is an administrator
     */
    const isAdmin = computed(() => hasRole('administrator'));

    /**
     * Check if user is a standard user
     */
    const isUser = computed(() => hasRole('user'));

    return {
        hasPermission,
        hasAnyPermission,
        hasAllPermissions,
        hasRole,
        hasAnyRole,
        isAdmin,
        isUser,
    };
}
