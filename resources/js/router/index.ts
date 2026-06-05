import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/modules/auth/stores/auth';

/**
 * Routes declare their access in `meta`:
 *   meta.public          true  — anonymous OK
 *   meta.permission      'a'   — require permission 'a'
 *   meta.permissionAny   ['a','b'] — require any of
 *   meta.permissionAll   ['a','b'] — require all of
 *
 * Module-level routes live under resources/js/modules/{m}/routes.ts and are
 * imported here lazily.
 */

declare module 'vue-router' {
    interface RouteMeta {
        public?: boolean;
        permission?: string;
        permissionAny?: string[];
        permissionAll?: string[];
        title?: string;
    }
}

const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/modules/auth/views/LoginView.vue'),
        meta: { public: true },
    },
    {
        path: '/accept-invitation',
        name: 'accept-invitation',
        component: () => import('@/modules/auth/views/AcceptInvitationView.vue'),
        meta: { public: true },
    },
    {
        path: '/forbidden',
        name: 'forbidden',
        component: () => import('@/views/ForbiddenView.vue'),
    },
    {
        path: '/',
        name: 'dashboard',
        component: () => import('@/views/DashboardView.vue'),
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: { name: 'dashboard' },
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.bootstrapped) {
        await auth.hydrate();
    }

    if (to.meta.public) return true;

    if (!auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.permission && !auth.can(to.meta.permission)) {
        return { name: 'forbidden' };
    }
    if (to.meta.permissionAny && !auth.canAny(to.meta.permissionAny)) {
        return { name: 'forbidden' };
    }
    if (to.meta.permissionAll && !auth.canAll(to.meta.permissionAll)) {
        return { name: 'forbidden' };
    }

    return true;
});
