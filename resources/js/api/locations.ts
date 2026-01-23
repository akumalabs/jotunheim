import api from '@/lib/axios';
import type { Location, ApiResponse } from '@/types/models';

export const locationApi = {
    list: async (): Promise<Location[]> => {
        const response = await api.get<ApiResponse<Location[]>>('/admin/locations');
        return response.data.data;
    },

    get: async (id: number): Promise<Location> => {
        const response = await api.get<ApiResponse<Location>>(`/admin/locations/${id}`);
        return response.data.data;
    },

    create: async (data: { name: string; short_code: string; description?: string }): Promise<Location> => {
        const response = await api.post<ApiResponse<Location>>('/admin/locations', data);
        return response.data.data;
    },

    update: async (id: number, data: Partial<Location>): Promise<Location> => {
        const response = await api.put<ApiResponse<Location>>(`/admin/locations/${id}`, data);
        return response.data.data;
    },

    delete: async (id: number): Promise<void> => {
        await api.delete(`/admin/locations/${id}`);
    },
};
