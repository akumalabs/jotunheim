<template>
    <BaseModal :open="open" title="Add IP Address" size="md" @close="$emit('close')">
        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
                <label class="label">IP Address</label>
                <input
                    v-model="form.address"
                    type="text"
                    placeholder="192.168.1.100"
                    class="input"
                    required
                />
            </div>

            <div>
                <label class="label">CIDR</label>
                <input
                    v-model.number="form.cidr"
                    type="number"
                    min="1"
                    max="32"
                    placeholder="24"
                    class="input"
                    required
                />
            </div>

            <div>
                <label class="label">Gateway</label>
                <input
                    v-model="form.gateway"
                    type="text"
                    placeholder="192.168.1.1"
                    class="input"
                    required
                />
            </div>

            <div>
                <label class="label">Type</label>
                <select v-model="form.type" class="input">
                    <option value="ipv4">IPv4</option>
                    <option value="ipv6">IPv6</option>
                </select>
            </div>

            <div class="flex items-center">
                <input
                    v-model="form.is_primary"
                    type="checkbox"
                    id="is-primary"
                    class="checkbox"
                />
                <label for="is-primary" class="ml-2 text-sm text-secondary-300">Set as primary IP</label>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-secondary-700">
                <button type="button" @click="$emit('close')" class="btn btn-secondary">
                    Cancel
                </button>
                <button type="submit" :disabled="isSubmitting" class="btn btn-primary">
                    <span v-if="isSubmitting">Adding...</span>
                    <span v-else>Add IP Address</span>
                </button>
            </div>
        </form>
    </BaseModal>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import BaseModal from './BaseModal.vue';

const props = defineProps<{
    open: boolean;
    serverId: number;
}>();

const emit = defineEmits<{
    close: [];
    success: [];
}>();

const form = ref({
    address: '',
    cidr: 24,
    gateway: '',
    type: 'ipv4',
    is_primary: false,
});

const isSubmitting = ref(false);

const handleSubmit = async () => {
    isSubmitting.value = true;
    try {
        // TODO: Implement API call
        // await adminServerApi.addIP(props.serverId, form.value);
        emit('success');
        emit('close');
        // Reset form
        form.value = {
            address: '',
            cidr: 24,
            gateway: '',
            type: 'ipv4',
            is_primary: false,
        };
    } catch (error) {
        console.error('Failed to add IP:', error);
        alert('Failed to add IP address');
    } finally {
        isSubmitting.value = false;
    }
};
</script>
