<script setup lang="ts">
import { ref } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { userApi } from '@/api';
import type { User } from '@/types/models';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    ShieldCheckIcon,
} from '@heroicons/vue/24/outline';

const queryClient = useQueryClient();

// Fetch users
const { data: users, isLoading } = useQuery({
    queryKey: ['admin', 'users'],
    queryFn: () => userApi.list(),
});

// Delete mutation
const deleteMutation = useMutation({
    mutationFn: (id: number) => userApi.delete(id),
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
    },
});

const confirmDelete = (user: User) => {
    if (confirm(`Delete user "${user.name}"? This cannot be undone.`)) {
        deleteMutation.mutate(user.id);
    }
};

// Modal state for create/edit
const showModal = ref(false);
const editingUser = ref<User | null>(null);
const formData = ref({
    name: '',
    email: '',
    password: '',
    is_admin: false,
});

const openCreate = () => {
    editingUser.value = null;
    formData.value = { name: '', email: '', password: '', is_admin: false };
    showModal.value = true;
};

const openEdit = (user: User) => {
    editingUser.value = user;
    formData.value = {
        name: user.name,
        email: user.email,
        password: '',
        is_admin: user.is_admin,
    };
    showModal.value = true;
};

// Create/Update mutation
const saveMutation = useMutation({
    mutationFn: async () => {
        if (editingUser.value) {
            const data: any = { ...formData.value };
            if (!data.password) delete data.password;
            return userApi.update(editingUser.value.id, data);
        } else {
            return userApi.create(formData.value);
        }
    },
    onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
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
                <h1 class="text-2xl font-bold text-white">Users</h1>
                <p class="text-secondary-400">Manage user accounts</p>
            </div>
            <button @click="openCreate" class="btn-primary">
                <PlusIcon class="w-5 h-5 mr-2" />
                Create User
            </button>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="card card-body text-center py-12">
            <div class="animate-pulse text-secondary-400">Loading users...</div>
        </div>

        <!-- Empty state -->
        <div v-else-if="!users?.length" class="card card-body text-center py-12">
            <h3 class="text-lg font-medium text-white mb-2">No users yet</h3>
            <p class="text-secondary-400">Create your first user to get started.</p>
        </div>

        <!-- Users table -->
        <div v-else class="card">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Servers</th>
                            <th>Created</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td class="font-medium text-white">{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td>
                                <span v-if="user.is_admin" class="badge-primary">
                                    <ShieldCheckIcon class="w-3 h-3 mr-1" />
                                    Admin
                                </span>
                                <span v-else class="badge-secondary">User</span>
                            </td>
                            <td>{{ user.servers_count ?? 0 }}</td>
                            <td>{{ new Date(user.created_at).toLocaleDateString() }}</td>
                            <td class="text-right space-x-2">
                                <button @click="openEdit(user)" class="btn-ghost btn-sm">
                                    <PencilIcon class="w-4 h-4" />
                                </button>
                                <button
                                    @click="confirmDelete(user)"
                                    class="btn-ghost btn-sm text-danger-500 hover:text-danger-400"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
                <div class="card relative z-10 w-full max-w-md">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold text-white">
                            {{ editingUser ? 'Edit User' : 'Create User' }}
                        </h2>
                    </div>
                    <form @submit.prevent="handleSubmit" class="card-body space-y-4">
                        <div>
                            <label class="label">Name</label>
                            <input v-model="formData.name" type="text" class="input" required />
                        </div>
                        <div>
                            <label class="label">Email</label>
                            <input v-model="formData.email" type="email" class="input" required />
                        </div>
                        <div>
                            <label class="label">
                                Password {{ editingUser ? '(leave blank to keep current)' : '' }}
                            </label>
                            <input
                                v-model="formData.password"
                                type="password"
                                class="input"
                                :required="!editingUser"
                                minlength="8"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                v-model="formData.is_admin"
                                type="checkbox"
                                id="is_admin"
                                class="rounded border-secondary-700 bg-secondary-800 text-primary-500"
                            />
                            <label for="is_admin" class="text-sm text-white">Administrator</label>
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
