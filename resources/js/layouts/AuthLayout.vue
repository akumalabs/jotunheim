<script setup lang="ts">
// Auth layout - minimal centered layout for login/register
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-bg px-4">
        <!-- Logo -->
        <div class="mb-8 text-center">
            <div class="inline-flex items-center justify-center gap-3 mb-4">
                <img src="/logo.svg" alt="Midgard Logo" class="w-16 h-16" />
            </div>
            <h1 class="text-4xl font-bold gradient-text">Midgard</h1>
            <p class="text-secondary-400 mt-2">Server Management Panel</p>
        </div>

        <!-- Content -->
        <div class="w-full max-w-lg">
            <RouterView />
        </div>

        <!-- Footer -->
        <p class="mt-8 text-secondary-600 text-sm">
            &copy; {{ new Date().getFullYear() }} Midgard. All rights reserved.
        </p>
    </div>
</template>

<style scoped>
.gradient-bg {
    background: linear-gradient(135deg, oklch(0.98 0.08 290 0.5 / 0.01) 100%);
}

@media (prefers-color-scheme: dark) {
    .gradient-bg {
        background: linear-gradient(135deg, oklch(0.98 0.08 290 0.5 / 0.01) 100%);
    }
}
</style>
