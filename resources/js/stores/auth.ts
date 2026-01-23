import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/lib/axios';

export interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
    is_admin: boolean;
    created_at: string;
}

export const useAuthStore = defineStore('auth', () => {
    // State - hydrate from localStorage
    const storedUser = localStorage.getItem('auth_user');
    const user = ref<User | null>(storedUser ? JSON.parse(storedUser) : null);
    const token = ref<string | null>(localStorage.getItem('auth_token'));
    const loading = ref(false);

    // Getters
    const isAuthenticated = computed(() => !!user.value && !!token.value);
    const isAdmin = computed(() => user.value?.is_admin ?? false);

    // Helper to persist user
    function persistUser(userData: User | null) {
        user.value = userData;
        if (userData) {
            localStorage.setItem('auth_user', JSON.stringify(userData));
        } else {
            localStorage.removeItem('auth_user');
        }
    }

    // Actions
    async function login(email: string, password: string): Promise<void> {
        loading.value = true;
        try {
            const response = await api.post('/auth/login', { email, password });
            token.value = response.data.token;
            localStorage.setItem('auth_token', response.data.token);
            persistUser(response.data.user);
        } finally {
            loading.value = false;
        }
    }

    async function logout(): Promise<void> {
        loading.value = true;
        try {
            await api.post('/auth/logout');
        } catch {
            // Ignore errors on logout
        } finally {
            persistUser(null);
            token.value = null;
            localStorage.removeItem('auth_token');
            loading.value = false;

            // Force page reload to ensure clean state
            window.location.href = '/auth/login';
        }
    }

    async function checkAuth(): Promise<void> {
        // If no token, clear user
        if (!token.value) {
            persistUser(null);
            return;
        }

        // If we have cached user data, use it immediately
        // Then validate with server in background
        loading.value = true;
        try {
            const response = await api.get('/auth/user');
            persistUser(response.data.data);
        } catch {
            // Token is invalid or expired
            persistUser(null);
            token.value = null;
            localStorage.removeItem('auth_token');
        } finally {
            loading.value = false;
        }
    }

    async function updateProfile(data: Partial<User>): Promise<void> {
        loading.value = true;
        try {
            const response = await api.patch('/auth/user', data);
            persistUser(response.data.data);
        } finally {
            loading.value = false;
        }
    }

    return {
        // State
        user,
        token,
        loading,
        // Getters
        isAuthenticated,
        isAdmin,
        // Actions
        login,
        logout,
        checkAuth,
        updateProfile,
    };
});
