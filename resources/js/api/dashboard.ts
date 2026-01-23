import axios from '@/lib/axios';

export const dashboardApi = {
    getStats: () => axios.get('/admin/dashboard').then(res => res.data),
};
