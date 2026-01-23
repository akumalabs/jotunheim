<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useForm } from 'vee-validate';
import { toTypedSchema } from '@vee-validate/zod';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const error = ref<string | null>(null);

const schema = toTypedSchema(
    z.object({
        email: z.string().email('Invalid email address'),
        password: z.string().min(1, 'Password is required'),
    })
);

const { defineField, handleSubmit, errors } = useForm({
    validationSchema: schema,
});

const [email, emailAttrs] = defineField('email');
const [password, passwordAttrs] = defineField('password');

const onSubmit = handleSubmit(async (values) => {
    error.value = null;
    try {
        await authStore.login(values.email, values.password);

        // Redirect based on admin status
        // Redirect based on admin status
        const redirect = route.query.redirect as string;
        
        if (authStore.isAdmin && (redirect === '/' || !redirect)) {
            router.push({ name: 'admin.dashboard' });
        } else if (redirect) {
            router.push(redirect);
        } else {
            router.push({ name: 'client.dashboard' });
        }
    } catch (e: any) {
        error.value = e?.message || 'Invalid credentials. Please try again.';
    }
});
</script>

<template>
    <div class="rounded-xl border bg-card text-card-foreground shadow animate-fade-in w-full">
        <div class="flex flex-col space-y-1.5 p-6">
            <h3 class="font-semibold leading-none tracking-tight">Sign in to your account</h3>
            <p class="text-sm text-muted-foreground">Enter your email and password to access the panel.</p>
        </div>

        <div class="p-6 pt-0">
            <!-- Error message -->
            <div
                v-if="error"
                class="mb-4 p-3 bg-destructive/15 text-destructive border border-destructive/20 rounded-md text-sm"
            >
                {{ error }}
            </div>

            <form @submit="onSubmit" class="space-y-4">
                <!-- Email -->
                <div class="space-y-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        v-model="email"
                        v-bind="emailAttrs"
                        placeholder="you@example.com"
                        :class="errors.email && 'border-destructive focus-visible:ring-destructive'"
                    />
                    <p v-if="errors.email" class="text-[0.8rem] font-medium text-destructive">
                        {{ errors.email }}
                    </p>
                </div>

                <!-- Password -->
                <div class="space-y-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        v-model="password"
                        v-bind="passwordAttrs"
                        placeholder="••••••••"
                        :class="errors.password && 'border-destructive focus-visible:ring-destructive'"
                    />
                    <p v-if="errors.password" class="text-[0.8rem] font-medium text-destructive">
                        {{ errors.password }}
                    </p>
                </div>

                <!-- Submit -->
                <Button
                    type="submit"
                    :disabled="authStore.loading"
                    class="w-full"
                >
                    <span v-if="authStore.loading">Signing in...</span>
                    <span v-else>Sign in</span>
                </Button>
            </form>
        </div>
    </div>
</template>
