import api from '@/lib/axios';
import type { Node, NodeStats, ApiResponse } from '@/types/models';

export const nodeApi = {
    // List all nodes
    list: async (): Promise<Node[]> => {
        const response = await api.get<ApiResponse<Node[]>>('/admin/nodes');
        return response.data.data;
    },

    // Get a single node
    get: async (id: number): Promise<Node> => {
        const response = await api.get<ApiResponse<Node>>(`/admin/nodes/${id}`);
        return response.data.data;
    },

    // Create a node
    create: async (data: Partial<Node> & { token_id: string; token_secret: string }): Promise<Node> => {
        const response = await api.post<ApiResponse<Node>>('/admin/nodes', data);
        return response.data.data;
    },

    // Update a node
    update: async (id: number, data: Partial<Node>): Promise<Node> => {
        const response = await api.put<ApiResponse<Node>>(`/admin/nodes/${id}`, data);
        return response.data.data;
    },

    // Delete a node
    delete: async (id: number): Promise<void> => {
        await api.delete(`/admin/nodes/${id}`);
    },

    // Test connection
    testConnection: async (id: number): Promise<{ success: boolean; message: string }> => {
        const response = await api.post(`/admin/nodes/${id}/test`);
        return response.data;
    },

    // Sync node resources
    sync: async (id: number): Promise<Node> => {
        const response = await api.post<ApiResponse<Node>>(`/admin/nodes/${id}/sync`);
        return response.data.data;
    },

    // Get node stats
    stats: async (id: number): Promise<NodeStats> => {
        const response = await api.get<ApiResponse<NodeStats>>(`/admin/nodes/${id}/stats`);
        return response.data.data;
    },

    // Get unmanaged VMs
    getUnmanaged: async (id: number): Promise<any[]> => {
        const response = await api.get<ApiResponse<any[]>>(`/admin/nodes/${id}/servers-unmanaged`);
        return response.data.data;
    },
};
