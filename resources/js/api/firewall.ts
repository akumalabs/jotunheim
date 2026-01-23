import axios from '@/lib/axios';

export const firewallApi = {
    // Get firewall rules and status
    getRules: async (serverId: number) => {
        const response = await axios.get(`/admin/servers/${serverId}/firewall`);
        return response.data;
    },

    // Enable firewall
    enable: async (serverId: number) => {
        const response = await axios.post(`/admin/servers/${serverId}/firewall/enable`);
        return response.data;
    },

    // Disable firewall
    disable: async (serverId: number) => {
        const response = await axios.post(`/admin/servers/${serverId}/firewall/disable`);
        return response.data;
    },

    // Create firewall rule
    createRule: async (serverId: number, data: any) => {
        const response = await axios.post(`/admin/servers/${serverId}/firewall/rules`, data);
        return response.data;
    },

    // Update firewall rule
    updateRule: async (serverId: number, ruleId: number, data: any) => {
        const response = await axios.put(`/admin/servers/${serverId}/firewall/rules/${ruleId}`, data);
        return response.data;
    },

    // Delete firewall rule
    deleteRule: async (serverId: number, ruleId: number) => {
        const response = await axios.delete(`/admin/servers/${serverId}/firewall/rules/${ruleId}`);
        return response.data;
    },

    // Apply pre-defined ruleset
    applyRuleset: async (serverId: number, template: string) => {
        const response = await axios.post(`/admin/servers/${serverId}/firewall/rulesets/${template}`);
        return response.data;
    },
};
