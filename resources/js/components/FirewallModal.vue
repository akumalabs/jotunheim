<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm } from 'vee-validate';
import { XMarkIcon, ShieldCheckIcon, PlusIcon, TrashIcon, LockClosedIcon, LockOpenIcon, AdjustmentsHorizontalIcon, CheckIcon } from '@heroicons/vue';
import { useApi } from '@/api/servers';

interface FirewallProps {
    server: ServerData;
    open: boolean;
    onUpdate: () => void;
}

interface ServerData {
    id: string;
    uuid: string;
    name: string;
    hostname: string;
    cpu: number;
    memory: number;
    disk: number;
    bandwidth_limit: number;
    bandwidth_usage: number;
    status: string;
    is_suspended: boolean;
}

interface FirewallRule {
    pos: number;
    type: string;
    action: string;
    protocol: string;
    source_port: number | null;
    destination_port: number;
    source: string | null;
    comment: string;
    enabled: boolean;
}

const schema = {
    type: 'string',
    action: 'string',
    protocol: 'string',
    destination_port: 'number',
    source_port: 'number',
    source: 'string',
    comment: 'string',
};

export default defineComponent({
    name: 'FirewallModal',
    props: defineProps<FirewallProps>(),
    setup(props) {
        const api = useApi();

        const { handleSubmit, values, errors, resetForm } = useForm({
            validationSchema: schema,
            initialValues: {
                type: 'in',
                action: 'ACCEPT',
                protocol: 'tcp',
                destination_port: 80,
                source_port: null,
                source: null,
                comment: '',
                enabled: true,
            },
            onSubmit: async (values) => {
                try {
                    await api.createFirewallRule(props.server.uuid, values);
                    resetForm();
                    props.onUpdate?.();
                } catch (error) {
                    console.error('Failed to create rule:', error);
                }
            },
        });

        const firewallEnabled = ref(false);
        const firewallRules = ref<FirewallRule[]>([]);

        const fetchFirewallStatus = async () => {
            try {
                const response = await fetch(`/api/v1/client/servers/${props.server.uuid}/firewall`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                        'Content-Type': 'application/json',
                    },
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to fetch firewall status');
                }

                const data = await response.json();

                firewallEnabled.value = data.data.enabled;
                firewallRules.value = data.data.rules || [];
            } catch (error) {
                console.error('Failed to fetch firewall status:', error);
            }
        };

        const handleEnable = async () => {
            try {
                const response = await fetch(`/api/v1/client/servers/${props.server.uuid}/firewall/enable`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                        'Content-Type': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to enable firewall');
                }

                firewallEnabled.value = true;
            } catch (error) {
                console.error('Failed to enable firewall:', error);
            }
        };

        const handleDisable = async () => {
            try {
                const response = await fetch(`/api/v1/client/servers/${props.server.uuid}/firewall/disable`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                        'Content-Type': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to disable firewall');
                }

                firewallEnabled.value = false;
            } catch (error) {
                console.error('Failed to disable firewall:', error);
            }
        };

        const handleApplyTemplate = async (template: string) => {
            try {
                const response = await fetch(`/api/v1/client/servers/${props.server.uuid}/firewall/templates`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        template,
                        rules: [],
                    }),
                });

                if (!response.ok) {
                    throw new Error('Failed to apply template');
                }

                await fetchFirewallStatus();
            } catch (error) {
                console.error('Failed to apply template:', error);
            }
        };

        const handleDeleteRule = async (pos: number) => {
            try {
                const response = await fetch(`/api/v1/client/servers/${props.server.uuid}/firewall/rules/${pos}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                        'Content-Type': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to delete rule');
                }

                await fetchFirewallStatus();
            } catch (error) {
                console.error('Failed to delete rule:', error);
            }
        };

        const handleUpdateRule = async (pos: number) => {
            try {
                const rule = firewallRules.value.find(r => r.pos === pos);

                if (!rule) {
                    return;
                }

                const response = await fetch(`/api/v1/client/servers/${props.server.uuid}/firewall/rules/${pos}`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: rule.action,
                        enabled: rule.enabled,
                        comment: rule.comment,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Failed to update rule');
                }

                await fetchFirewallStatus();
            } catch (error) {
                console.error('Failed to update rule:', error);
            }
        };

        const getRuleIcon = (type: string, action: string) => {
            if (action === 'ACCEPT') {
                return type === 'in' ? LockOpenIcon : LockClosedIcon;
            }
            return TrashIcon;
        };

        const getProtocolBadge = (protocol: string) => {
            const colors: Record<string, string> = {
                tcp: 'bg-blue-500/20 text-blue-100',
                udp: 'bg-purple-500/20 text-purple-100',
                icmp: 'bg-green-500/20 text-green-100',
            };

            const labels: Record<string, string> = {
                tcp: 'TCP',
                udp: 'UDP',
                icmp: 'ICMP',
            };

            return colors[protocol] + ' ' + labels[protocol];
        };

        const getActionBadge = (action: string) => {
            const colors: Record<string, string> = {
                ACCEPT: 'bg-green-500/20 text-green-100',
                DROP: 'bg-danger-500/20 text-danger-100',
                REJECT: 'bg-warning-500/20 text-warning-100',
            };

            return colors[action] + ' ' + action;
        };

        const rulesByType = computed(() => {
            const rules: Record<string, FirewallRule[]> = {
                in: [],
                out: [],
            };

            firewallRules.value.forEach(rule => {
                if (!rules[rule.type]) {
                    rules[rule.type] = [];
                }
                rules[rule.type].push(rule);
            });

            return rules;
        });

        onMounted(() => {
            if (props.open) {
                fetchFirewallStatus();
            }
        });

        return () => resetForm();
    },
});
</script>

<template>
    <div class="max-w-6xl mx-auto p-6">
        <h2 class="text-3xl font-bold text-white mb-6">
            Firewall Configuration
        </h2>

        <div class="space-y-6">
            <div class="bg-card border border-secondary-800 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <h3 class="text-xl font-semibold text-white">
                            Status
                        </h3>
                        <div class="flex items-center gap-2 px-3 py-1 rounded-full" :class="firewallEnabled ? 'bg-success-500/20 text-success-100' : 'bg-warning-500/20 text-warning-100'">
                            <LockClosedIcon v-if="firewallEnabled" class="w-4 h-4" />
                            <LockOpenIcon v-else class="w-4 h-4" />
                            <span class="ml-2 text-sm font-medium">
                                {{ firewallEnabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            v-if="!firewallEnabled"
                            @click="handleEnable"
                            class="btn-primary"
                        >
                            <ShieldCheckIcon class="w-4 h-4 mr-2" />
                            Enable
                        </button>
                        <button
                            v-else
                            @click="handleDisable"
                            class="btn-secondary"
                        >
                            <LockClosedIcon class="w-4 h-4 mr-2" />
                            Disable
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
                    <button
                        @click="handleApplyTemplate('web')"
                        class="bg-secondary-800 hover:bg-secondary-700 border border-secondary-700 rounded-lg p-4 text-center transition-colors duration-200"
                    >
                        <div class="text-2xl mb-2">🌐</div>
                        <div class="text-sm font-medium text-secondary-300">Web</div>
                        <div class="text-xs text-secondary-400 mt-1">
                            HTTP (80) + HTTPS (443)
                        </div>
                    </button>

                    <button
                        @click="handleApplyTemplate('ssh')"
                        class="bg-secondary-800 hover:bg-secondary-700 border border-secondary-700 rounded-lg p-4 text-center transition-colors duration-200"
                    >
                        <div class="text-2xl mb-2">🔑</div>
                        <div class="text-sm font-medium text-secondary-300">SSH</div>
                        <div class="text-xs text-secondary-400 mt-1">
                            Port 22
                        </div>
                    </button>

                    <button
                        @click="handleApplyTemplate('custom')"
                        class="bg-secondary-800 hover:bg-secondary-700 border border-secondary-700 rounded-lg p-4 text-center transition-colors duration-200"
                    >
                        <div class="text-2xl mb-2">⚙️</div>
                        <div class="text-sm font-medium text-secondary-300">Custom</div>
                        <div class="text-xs text-secondary-400 mt-1">
                            Create your own rules
                        </div>
                    </button>
                </div>
            </div>

            <div class="bg-card border border-secondary-800 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-white">
                        Firewall Rules
                    </h3>
                    <button
                        @click="resetForm(); modalOpen = true"
                        class="btn-primary"
                    >
                        <PlusIcon class="w-4 h-4 mr-2" />
                        Add Rule
                    </button>
                </div>

                <div v-if="firewallRules.length === 0" class="text-center py-8 text-secondary-400">
                    <LockClosedIcon class="w-12 h-12 mx-auto mb-4 text-secondary-600" />
                    <p>No firewall rules configured</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="rule in firewallRules"
                        :key="rule.pos"
                        class="bg-secondary-900 rounded-lg p-4 border border-secondary-800"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="px-3 py-1 rounded-full" :class="getActionBadge(rule.action)">
                                    {{ rule.action }}
                                </div>
                                <div class="px-3 py-1 rounded-full" :class="getProtocolBadge(rule.protocol)">
                                    {{ rule.protocol }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    @click="handleUpdateRule(rule.pos)"
                                    class="p-1 hover:bg-secondary-800 rounded transition-colors duration-200"
                                    :title="rule.enabled ? 'Disable rule' : 'Enable rule'"
                                >
                                    <AdjustmentsHorizontalIcon class="w-4 h-4" />
                                </button>
                                <button
                                    @click="handleDeleteRule(rule.pos)"
                                    class="p-1 hover:bg-secondary-800 text-danger-500 rounded transition-colors duration-200"
                                    title="Delete rule"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-3 text-sm">
                            <div class="flex items-center gap-4 text-secondary-400">
                                <div>
                                    <span class="font-medium">Protocol:</span>
                                    {{ rule.protocol }}
                                </div>
                                <div v-if="rule.destination_port">
                                    <span class="font-medium">Destination Port:</span>
                                    {{ rule.destination_port }}
                                </div>
                                <div v-if="rule.source_port">
                                    <span class="font-medium">Source Port:</span>
                                    {{ rule.source_port }}
                                </div>
                            </div>

                            <div v-if="rule.comment" class="text-secondary-300">
                                {{ rule.comment }}
                            </div>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <div class="flex items-center gap-2 px-3 py-1 rounded-full" :class="rule.enabled ? 'bg-success-500/20 text-success-100' : 'bg-secondary-800 text-secondary-500'">
                                <CheckIcon v-if="rule.enabled" class="w-4 h-4" />
                                <span class="text-xs font-medium">{{ rule.enabled ? 'Active' : 'Inactive' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.btn-primary {
    @apply 'px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors duration-200';
}

.btn-secondary {
    @apply 'px-4 py-2 bg-secondary-800 hover:bg-secondary-700 text-secondary-200 rounded-lg font-medium transition-colors duration-200';
}
</style>
