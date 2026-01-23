<script setup lang="ts">
import { ref } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { locationApi } from '@/api';
import type { Location } from '@/types/models';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    MapPinIcon,
} from '@heroicons/vue/24/outline';

const queryClient = useQueryClient();

// Fetch locations
const { data: locations, isLoading } = useQuery({
    queryKey: ['admin', 'locations'],
    queryFn: () => locationApi.list(),
});

// Delete mutation
const deleteMutation = useMutation({
    mutationFn: (id: number) => locationApi.delete(id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'locations'] });
    },
});

const confirmDelete = (location: Location) => {
    if (confirm(`Delete location "${location.name}"?`)) {
        deleteMutation.mutate(location.id);
    }
};

// Modal state
const showModal = ref(false);
const editingLocation = ref<Location | null>(null);
const formData = ref({
    name: '',
    short_code: '',
    description: '',
});

const openCreate = () => {
    editingLocation.value = null;
    formData.value = { name: '', short_code: '', description: '' };
    showModal.value = true;
};

const openEdit = (location: Location) => {
    editingLocation.value = location;
    formData.value = {
        name: location.name,
        short_code: location.short_code,
        description: location.description || '',
    };
    showModal.value = true;
};

// Save mutation
const saveMutation = useMutation({
    mutationFn: async () => {
        if (editingLocation.value) {
            return locationApi.update(editingLocation.value.id, formData.value);
        } else {
            return locationApi.create(formData.value);
        }
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'locations'] });
        showModal.value = false;
    },
});

const handleSubmit = () => {
    saveMutation.mutate();
};
</script>

<template>
    <div class="p-6 space-y-6 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Locations</h1>
                <p class="text-secondary-400">Manage datacenter locations</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-5 h-5 mr-2" />
                Add Location
            </button>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="card card-body text-center py-12">
            <div class="animate-pulse text-secondary-400">Loading locations...</div>
        </div>

        <!-- Empty state -->
        <div v-else-if="!locations?.length" class="card card-body text-center py-12">
            <MapPinIcon class="w-12 h-12 mx-auto mb-4 text-secondary-500" />
            <h3 class="text-lg font-medium text-white mb-2">No locations yet</h3>
            <p class="text-secondary-400">Add your first datacenter location.</p>
        </div>

        <!-- Locations grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="location in locations" :key="location.id" class="card">
                <div class="card-body">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="font-semibold text-white">{{ location.name }}</h3>
                            <span class="badge-secondary mt-1">{{ location.short_code }}</span>
                        </div>
                        <div class="flex gap-1">
                            <button @click="openEdit(location)" class="btn-ghost btn-sm">
                                <PencilIcon class="w-4 h-4" />
                            </button>
                            <button
                                @click="confirmDelete(location)"
                                class="btn-ghost btn-sm text-danger-500"
                            >
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    <p v-if="location.description" class="text-sm text-secondary-400 mb-4">
                        {{ location.description }}
                    </p>
                    <div class="text-sm text-secondary-500">
                        {{ location.nodes_count ?? 0 }} nodes
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
                <div class="card relative z-10 w-full max-w-md">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-white">
                            {{ editingLocation ? 'Edit Location' : 'Add Location' }}
                        </h2>
                    </div>
                    <form @submit.prevent="handleSubmit" class="card-body space-y-4">
                        <div>
                            <label class="label">Name</label>
                            <input v-model="formData.name" type="text" class="input" required />
                        </div>
                        <div>
                            <label class="label">Short Code</label>
                            <input
                                v-model="formData.short_code"
                                type="text"
                                class="input"
                                maxlength="10"
                                placeholder="e.g., AMS, NYC, SG"
                                required
                            />
                        </div>
                        <div>
                            <label class="label">Description (optional)</label>
                            <textarea v-model="formData.description" class="input" rows="3"></textarea>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showModal = false" class="btn-secondary flex-1">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="saveMutation.isPending.value"
                                class="btn-primary flex-1"
                            >
                                {{ saveMutation.isPending.value ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
