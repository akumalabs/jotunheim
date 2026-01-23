import api from '@/lib/axios';
import type { User, ApiResponse } from '@/types/models';

export const userApi = {
    list: async (): Promise<User[]> => {
        const response = await api.get<ApiResponse<User[]>>('/admin/users');
        return response.data.data;
    },

    get: async (id: number): Promise<User> => {
        const response = await api.get<ApiResponse<User>>(`/admin/users/${id}`);
        return response.data.data;
    },

    create: async (data: { name: string; email: string; password: string; is_admin?: boolean }): Promise<User> => {
        const response = await api.post<ApiResponse<User>>('/admin/users', data);
        return response.data.data;
    },

    update: async (id: number, data: Partial<User> & { password?: string }): Promise<User> => {
        const response = await api.put<ApiResponse<User>>(`/admin/users/${id}`, data);
        return response.data.data;
    },

    delete: async (id: number): Promise<void> => {
        await api.delete(`/admin/users/${id}`);
    },
};
