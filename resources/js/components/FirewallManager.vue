<script setup lang="ts">
import { ref, computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { firewallApi } from '@/api/firewall';
import AddFirewallRuleModal from './AddFirewallRuleModal.vue';
import LoadingSpinner from './LoadingSpinner.vue';

const props = defineProps<{
    serverId: number;
    server: any;
}>();

const queryClient = useQueryClient();
const showAddRuleModal = ref(false);
const editingRule = ref<any>(null);

// Fetch firewall rules
const { data: firewallData, isLoading } = useQuery({
    queryKey: ['firewall-rules', props.serverId],
    queryFn: () => firewallApi.getRules(props.serverId),
    refetchInterval: 5000,
});

const rules = computed(() => firewallData.value?.data || []);
const firewallEnabled = computed(() => firewallData.value?.firewall_enabled || false);

// Enable/Disable firewall
const toggleMutation = useMutation({
    mutationFn: () => {
        return firewallEnabled.value 
            ? firewallApi.disable(props.serverId)
            : firewallApi.enable(props.serverId);
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['firewall-rules', props.serverId] });
        queryClient.invalidateQueries({ queryKey: ['admin', 'servers', props.serverId] });
    },
    onError: (error: any) => {
        alert(error.response?.data?.message || 'Failed to toggle firewall');
    },
});

// Delete rule
const deleteMutation = useMutation({
    mutationFn: (ruleId: number) => firewallApi.deleteRule(props.serverId, ruleId),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['firewall-rules', props.serverId] });
    },
    onError: (error: any) => {
        alert(error.response?.data?.message || 'Failed to delete rule');
    },
});

// Apply ruleset
const rulesetMutation = useMutation({
    mutationFn: (template: string) => firewallApi.applyRuleset(props.serverId, template),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['firewall-rules', props.serverId] });
    },
    onError: (error: any) => {
        alert(error.response?.data?.message || 'Failed to apply ruleset');
    },
});

const handleDelete = (rule: any) => {
    if (confirm(`Delete firewall rule "${rule.name || 'Unnamed'}"?`)) {
        deleteMutation.mutate(rule.id);
    }
};

const handleEdit = (rule: any) => {
    editingRule.value = rule;
    showAddRuleModal.value = true;
};

const handleApplyRuleset = (template: string) => {
    if (confirm(`Apply ${template} ruleset? This will add pre-defined rules.`)) {
        rulesetMutation.mutate(template);
    }
};

const closeModal = () => {
    showAddRuleModal.value = false;
    editingRule.value = null;
};
</script>

<template>
    <div class="glass-card border border-secondary-700/50 rounded-xl p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-semibold text-white mb-1">Firewall Management</h3>
                <p class="text-sm text-secondary-400">Manage iptables rules for this server</p>
            </div>
            <div class="flex items-center gap-4">
                <!-- Firewall Toggle -->
                <div class="flex items-center gap-3">
                    <span class="text-sm text-secondary-400">Firewall:</span>
                    <button
                        @click="toggleMutation.mutate()"
                        :disabled="toggleMutation.isPending.value"
                        :class="[
                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                            firewallEnabled ? 'bg-green-500' : 'bg-gray-600'
                        ]"
                    >
                        <span
                            :class="[
                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                firewallEnabled ? 'translate-x-6' : 'translate-x-1'
                            ]"
                        />
                    </button>
                    <span :class="[
                        'px-3 py-1 rounded-full text-sm font-medium',
                        firewallEnabled ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'
                    ]">
                        {{ firewallEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-between items-center mb-4">
            <div class="flex gap-2">
                <button @click="showAddRuleModal = true" class="px-3 py-1.5 bg-primary-500 hover:bg-primary-600 text-white rounded-lg transition-colors flex items-center gap-1.5 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Rule
                </button>
                
                <!-- Apply Ruleset Dropdown -->
                <div class="relative">
                    <select 
                        @change="(e) => { const val = (e.target as HTMLSelectElement).value; if(val) handleApplyRuleset(val); (e.target as HTMLSelectElement).value = ''; }"
                        class="px-3 py-1.5 bg-secondary-700 hover:bg-secondary-600 text-white rounded-lg transition-colors text-sm font-medium pr-8 appearance-none cursor-pointer"
                    >
                        <option value="">Apply Ruleset...</option>
                        <option value="web-server">Web Server</option>
                        <option value="ssh-only">SSH Only</option>
                        <option value="database">Database</option>
                        <option value="mail-server">Mail Server</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Rules Table -->
        <div v-if="isLoading" class="flex justify-center py-12">
            <LoadingSpinner text="Loading firewall rules..." />
        </div>

        <div v-else-if="rules.length === 0" class="text-center py-12">
            <svg class="w-16 h-16 mx-auto text-secondary-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
            <p class="text-secondary-400">No firewall rules configured</p>
            <p class="text-sm text-secondary-500 mt-2">Add a rule or apply a pre-defined ruleset to get started</p>
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-secondary-700">
                        <th class="text-left py-3 px-4 text-sm font-medium text-secondary-400">Name</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-secondary-400">Direction</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-secondary-400">Action</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-secondary-400">Protocol</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-secondary-400">Port</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-secondary-400">IP Version</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-secondary-400">Status</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-secondary-400">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="rule in rules"
                        :key="rule.id"
                        class="border-b border-secondary-800 hover:bg-secondary-800/30 transition-colors"
                    >
                        <td class="py-3 px-4">
                            <span class="text-sm text-white font-medium">{{ rule.name || 'Unnamed Rule' }}</span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 text-xs rounded bg-blue-500/20 text-blue-300">
                                {{ rule.direction.toUpperCase() }}
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <span :class="[
                                'px-2 py-1 text-xs rounded',
                                rule.action === 'allow' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'
                            ]">
                                {{ rule.action.toUpperCase() }}
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="text-sm text-secondary-300">{{ rule.protocol.toUpperCase() }}</span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="text-sm text-secondary-300">{{ rule.dest_port || '-' }}</span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="text-sm text-secondary-300">{{ rule.ip_version.toUpperCase() }}</span>
                        </td>
                        <td class="py-3 px-4">
                            <span :class="[
                                'px-2 py-1 text-xs rounded',
                                rule.enabled ? 'bg-green-500/20 text-green-300' : 'bg-gray-500/20 text-gray-400'
                            ]">
                                {{ rule.enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex justify-end gap-2">
                                <button
                                    @click="handleEdit(rule)"
                                    class="text-blue-400 hover:text-blue-300 transition-colors"
                                    title="Edit"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    @click="handleDelete(rule)"
                                    class="text-red-400 hover:text-red-300 transition-colors"
                                    title="Delete"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Add/Edit Rule Modal -->
        <AddFirewallRuleModal
            :open="showAddRuleModal"
            :server-id="serverId"
            :rule="editingRule"
            @close="closeModal"
            @success="closeModal"
        />
    </div>
</template>
