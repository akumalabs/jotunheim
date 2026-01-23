import api from '@/lib/axios';

export interface TemplateGroup {
    id: number;
    node_id: number;
    name: string;
    icon?: string;
    order: number;
    templates: Template[];
}

export interface Template {
    id: number;
    template_group_id: number;
    name: string;
    vmid: string;
    min_memory: number;
    min_disk: number;
    is_visible: boolean;
}

export const templateApi = {
    async listGroups(nodeId: number): Promise<TemplateGroup[]> {
        const response = await api.get(`/admin/nodes/${nodeId}/templates`);
        return response.data.data;
    },

    async syncFromProxmox(nodeId: number): Promise<void> {
        await api.post(`/admin/nodes/${nodeId}/templates/sync`);
    },
};
