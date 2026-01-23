import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';
import router from '@/router';
import App from '@/App.vue';
import '@/lib/axios';

const app = createApp(App);

// Pinia for state management
const pinia = createPinia();
app.use(pinia);

// Vue Query for data fetching
app.use(VueQueryPlugin, {
    queryClientConfig: {
        defaultOptions: {
            queries: {
                staleTime: 1000 * 60, // 1 minute
                refetchOnWindowFocus: false,
                retry: 1,
            },
        },
    },
});

// Vue Router
app.use(router);

// Mount app
app.mount('#app');
