import api from '@/lib/axios';
import type { Server, ServerStatus, ApiResponse } from '@/types/models';

// Admin server API
export const adminServerApi = {
    list: async (filters?: { status?: string; node_id?: number; user_id?: number }): Promise<Server[]> => {
        const response = await api.get<ApiResponse<Server[]>>('/admin/servers', { params: filters });
        return response.data.data;
    },

    get: async (id: number): Promise<Server> => {
        const response = await api.get<ApiResponse<Server>>(`/admin/servers/${id}`);
        return response.data.data;
    },

    create: async (data: {
        user_id: number;
        node_id: number;
        name: string;
        hostname?: string;
        cpu: number;
        memory: number;
        disk: number;
        template_vmid?: string; // Optional for adoption
        bandwidth_limit?: number | null;
        is_adoption?: boolean;
        vmid?: number | string;
        ip_address?: string;
        address_pool_id?: number | string;
    }): Promise<Server> => {
        const response = await api.post<ApiResponse<Server>>('/admin/servers', data);
        return response.data.data;
    },

    update: async (id: number, data: Partial<Server>): Promise<Server> => {
        const response = await api.put<ApiResponse<Server>>(`/admin/servers/${id}`, data);
        return response.data.data;
    },

    delete: async (id: number, purge: boolean = true): Promise<void> => {
        await api.delete(`/admin/servers/${id}`, { params: { purge } });
    },

    power: async (id: number, action: 'start' | 'stop' | 'restart' | 'shutdown' | 'reset'): Promise<{ status: string }> => {
        const response = await api.post(`/admin/servers/${id}/power`, { action });
        return response.data.data;
    },

    status: async (id: number): Promise<ServerStatus> => {
        const response = await api.get<ApiResponse<ServerStatus>>(`/admin/servers/${id}/status`);
        return response.data.data;
    },

    rebuild: async (id: number, data: { template_vmid: string; password?: string }): Promise<{ status: string }> => {
        const response = await api.post(`/admin/servers/${id}/rebuild`, data);
        return response.data.data;
    },

    updateResources: async (id: number, data: {
        cpu?: number;
        memory?: number;
        disk?: number;
        bandwidth_limit?: number | null;
    }): Promise<Server> => {
        const response = await api.patch<ApiResponse<Server>>(`/admin/servers/${id}/resources`, data);
        return response.data.data;
    },

    resetPassword: async (id: number, data: { password: string }): Promise<void> => {
        await api.post(`/admin/servers/${id}/reset-password`, data);
    },

    rrdData: async (id: number, timeframe: string = 'hour'): Promise<any[]> => {
        const response = await api.get(`/admin/servers/${id}/rrd`, { params: { timeframe } });
        return response.data.data;
    },

    getUsageData: async (id: number, timeframe: string = 'hour'): Promise<any[]> => {
        const response = await api.get(`/admin/servers/${id}/rrd`, { params: { timeframe } });
        return response.data.data;
    },

    getAvailableIPs: async (id: number): Promise<{ data: any[]; total: number }> => {
        const response = await api.get(`/admin/servers/${id}/network/available-ips`);
        return response.data;
    },

    assignIP: async (id: number, data: { address_id: number }): Promise<any> => {
        const response = await api.post(`/admin/servers/${id}/network/assign-ip`, data);
        return response.data;
    },

    removeIP: async (id: number, addressId: number): Promise<void> => {
        await api.delete(`/admin/servers/${id}/network/addresses/${addressId}`);
    },

    setPrimaryIP: async (id: number, addressId: number): Promise<void> => {
        await api.post(`/admin/servers/${id}/network/addresses/${addressId}/set-primary`);
    },

    updateNetwork: async (id: number): Promise<void> => {
        await api.post(`/admin/servers/${id}/network/update`);
    },

    installProgress: async (id: number): Promise<{ progress: number; status: string; step?: string }> => {
        const response = await api.get(`/admin/servers/${id}/install-progress`);
        return response.data;
    },
};

// Client server API
export const clientServerApi = {
    list: async (): Promise<Server[]> => {
        const response = await api.get<ApiResponse<Server[]>>('/client/servers');
        return response.data.data;
    },

    get: async (uuid: string): Promise<Server> => {
        const response = await api.get<ApiResponse<Server>>(`/client/servers/${uuid}`);
        return response.data.data;
    },

    status: async (uuid: string): Promise<ServerStatus> => {
        const response = await api.get<ApiResponse<ServerStatus>>(`/client/servers/${uuid}/status`);
        return response.data.data;
    },

    power: async (uuid: string, action: 'start' | 'stop' | 'restart' | 'shutdown' | 'kill'): Promise<{ status: string }> => {
        const response = await api.post(`/client/servers/${uuid}/power`, { action });
        return response.data.data;
    },

    console: async (uuid: string): Promise<{ ticket: string; port: number; url: string }> => {
        const response = await api.get(`/client/servers/${uuid}/console`);
        return response.data.data;
    },

    updatePassword: async (uuid: string, password: string): Promise<{ message: string }> => {
        const response = await api.post(`/client/servers/${uuid}/settings/password`, { password });
        return response.data;
    },

    mountIso: async (uuid: string, storage: string, iso: string): Promise<{ message: string }> => {
        const response = await api.post(`/client/servers/${uuid}/settings/iso/mount`, { storage, iso });
        return response.data;
    },

    unmountIso: async (uuid: string): Promise<{ message: string }> => {
        const response = await api.post(`/client/servers/${uuid}/settings/iso/unmount`);
        return response.data;
    },

    // Snapshots API
    listSnapshots: async (uuid: string): Promise<any[]> => {
        const response = await api.get(`/client/servers/${uuid}/snapshots`);
        return response.data.data;
    },

    createSnapshot: async (uuid: string, name: string, description?: string, includeRam?: boolean): Promise<{ message: string; upid: string }> => {
        const response = await api.post(`/client/servers/${uuid}/snapshots`, {
            name,
            description,
            include_ram: includeRam
        });
        return response.data;
    },

    rollbackSnapshot: async (uuid: string, name: string): Promise<{ message: string; upid: string }> => {
        const response = await api.post(`/client/servers/${uuid}/snapshots/${name}/rollback`);
        return response.data;
    },

    deleteSnapshot: async (uuid: string, name: string): Promise<{ message: string; upid: string }> => {
        const response = await api.delete(`/client/servers/${uuid}/snapshots/${name}`);
        return response.data;
    },

    // Reinstall
    reinstall: async (uuid: string, templateId: number, password: string): Promise<{ message: string }> => {
        const response = await api.post(`/client/servers/${uuid}/settings/reinstall`, {
            template_id: templateId,
            password
        });
        return response.data;
    },
};
