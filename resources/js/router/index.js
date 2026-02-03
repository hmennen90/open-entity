import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        name: 'home',
        component: () => import('../views/Home.vue'),
    },
    {
        path: '/chat',
        name: 'chat',
        component: () => import('../views/Chat.vue'),
    },
    {
        path: '/mind',
        name: 'mind',
        component: () => import('../views/MindViewer.vue'),
    },
    {
        path: '/memory',
        name: 'memory',
        component: () => import('../views/Memory.vue'),
    },
    {
        path: '/goals',
        name: 'goals',
        component: () => import('../views/Goals.vue'),
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('../views/Settings.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
