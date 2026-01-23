<script setup lang="ts">
import { ref } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { clientServerApi } from '@/api';
import {
    CameraIcon,
    ArrowPathIcon,
    TrashIcon,
    ClockIcon,
    PlusIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    serverUuid: string;
}>();

const queryClient = useQueryClient();
const showCreateModal = ref(false);
const newSnapshotName = ref('');
const newSnapshotDescription = ref('');
const includeRam = ref(false);

// Fetch snapshots
const { data: snapshots, isLoading, refetch } = useQuery({
    queryKey: ['client', 'server', props.serverUuid, 'snapshots'],
    queryFn: () => clientServerApi.listSnapshots(props.serverUuid),
});

// Create snapshot
const createMutation = useMutation({
    mutationFn: () => clientServerApi.createSnapshot(
        props.serverUuid, 
        newSnapshotName.value,
        newSnapshotDescription.value || undefined,
        includeRam.value
    ),
    onSuccess: () => {
        showCreateModal.value = false;
        newSnapshotName.value = '';
        newSnapshotDescription.value = '';
        includeRam.value = false;
        refetch();
    },
});

// Rollback snapshot
const rollbackMutation = useMutation({
    mutationFn: (name: string) => clientServerApi.rollbackSnapshot(props.serverUuid, name),
    onSuccess: () => {
        refetch();
        queryClient.invalidateQueries({ queryKey: ['client', 'server', props.serverUuid] });
    },
});

// Delete snapshot
const deleteMutation = useMutation({
    mutationFn: (name: string) => clientServerApi.deleteSnapshot(props.serverUuid, name),
    onSuccess: () => {
        refetch();
    },
});

const formatDate = (timestamp: number) => {
    if (!timestamp) return 'Unknown';
    return new Date(timestamp * 1000).toLocaleString();
};

const handleRollback = (name: string) => {
    if (confirm(`Are you sure you want to rollback to snapshot "${name}"? The server will be stopped and restored to this snapshot state.`)) {
        rollbackMutation.mutate(name);
    }
};

const handleDelete = (name: string) => {
    if (confirm(`Are you sure you want to delete snapshot "${name}"? This action cannot be undone.`)) {
        deleteMutation.mutate(name);
    }
};
</script>

<template>
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white flex items-center">
                <CameraIcon class="w-5 h-5 mr-2" />
                Snapshots
            </h2>
            <button @click="showCreateModal = true" class="btn-primary text-sm">
                <PlusIcon class="w-4 h-4 mr-1" />
                Create Snapshot
            </button>
        </div>
        
        <div class="card-body">
            <!-- Loading -->
            <div v-if="isLoading" class="animate-pulse space-y-3">
                <div class="h-12 bg-secondary-800 rounded"></div>
                <div class="h-12 bg-secondary-800 rounded"></div>
            </div>

            <!-- Empty -->
            <div v-else-if="!snapshots?.length" class="text-center py-8 text-secondary-500">
                <CameraIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
                <p>No snapshots yet</p>
                <p class="text-sm">Create a snapshot to save the current state of your server</p>
            </div>

            <!-- Snapshots List -->
            <div v-else class="divide-y divide-secondary-700">
                <div 
                    v-for="snapshot in snapshots" 
                    :key="snapshot.name"
                    class="py-3 flex items-center justify-between"
                >
                    <div>
                        <p class="text-white font-medium">{{ snapshot.name }}</p>
                        <p class="text-secondary-400 text-sm flex items-center gap-2">
                            <ClockIcon class="w-4 h-4" />
                            {{ formatDate(snapshot.snaptime) }}
                            <span v-if="snapshot.description" class="text-secondary-500">
                                — {{ snapshot.description }}
                            </span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="handleRollback(snapshot.name)"
                            :disabled="rollbackMutation.isPending.value"
                            class="btn-secondary text-sm px-3 py-1.5"
                            title="Rollback to this snapshot"
                        >
                            <ArrowPathIcon class="w-4 h-4" />
                        </button>
                        <button
                            @click="handleDelete(snapshot.name)"
                            :disabled="deleteMutation.isPending.value"
                            class="btn-danger-outline text-sm px-3 py-1.5"
                            title="Delete snapshot"
                        >
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Snapshot Modal -->
    <Teleport to="body">
        <div 
            v-if="showCreateModal" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="showCreateModal = false"
        >
            <div class="bg-secondary-900 rounded-xl shadow-2xl w-full max-w-md border border-secondary-700 animate-fade-in">
                <div class="flex items-center justify-between p-4 border-b border-secondary-700">
                    <h2 class="text-lg font-semibold text-white">Create Snapshot</h2>
                    <button @click="showCreateModal = false" class="text-secondary-400 hover:text-white">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-secondary-300 mb-1">
                            Snapshot Name <span class="text-red-400">*</span>
                        </label>
                        <input
                            v-model="newSnapshotName"
                            type="text"
                            class="input w-full"
                            placeholder="e.g., before-update"
                            pattern="[a-zA-Z0-9_-]+"
                        />
                        <p class="text-xs text-secondary-500 mt-1">
                            Only letters, numbers, underscores, and hyphens allowed
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-secondary-300 mb-1">Description</label>
                        <input
                            v-model="newSnapshotDescription"
                            type="text"
                            class="input w-full"
                            placeholder="Optional description"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            v-model="includeRam"
                            type="checkbox"
                            id="includeRam"
                            class="rounded border-secondary-600 bg-secondary-800 text-primary-500 focus:ring-primary-500"
                        />
                        <label for="includeRam" class="text-sm text-secondary-300">
                            Include RAM (requires more storage)
                        </label>
                    </div>

                    <button
                        @click="createMutation.mutate()"
                        :disabled="createMutation.isPending.value || !newSnapshotName"
                        class="btn-primary w-full"
                    >
                        <ArrowPathIcon v-if="createMutation.isPending.value" class="w-4 h-4 animate-spin mr-2" />
                        Create Snapshot
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
