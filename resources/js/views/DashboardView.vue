<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/modules/auth/stores/auth';
import OrganizationSwitcher from '@/components/OrganizationSwitcher.vue';

const auth = useAuthStore();
const router = useRouter();

async function logout(): Promise<void> {
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="mx-auto max-w-5xl p-8">
        <header class="mb-8 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Dashboard</h1>
                <p class="text-sm text-slate-500">
                    Signed in as {{ auth.user?.name }} ({{ auth.user?.email }})
                    <span v-if="auth.isSuperAdmin" class="ml-2 rounded bg-rose-100 px-2 py-0.5 text-xs text-rose-700">
                        Super Admin
                    </span>
                </p>
            </div>
            <div class="flex items-center gap-4">
                <OrganizationSwitcher />
                <button
                    class="rounded-md border border-slate-300 px-3 py-1 text-sm hover:bg-slate-100"
                    @click="logout"
                >
                    Sign out
                </button>
            </div>
        </header>

        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-6">
            <h2 class="mb-3 text-sm font-medium uppercase tracking-wide text-slate-500">
                Memberships
            </h2>
            <ul class="space-y-1 text-sm">
                <li v-for="m in auth.memberships" :key="m.organization_id">
                    {{ m.organization_name }}
                    <span class="text-slate-500">— {{ m.default_role ?? 'member' }}</span>
                </li>
                <li v-if="auth.memberships.length === 0" class="text-slate-500">
                    No active memberships.
                </li>
            </ul>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6">
            <h2 class="mb-3 text-sm font-medium uppercase tracking-wide text-slate-500">
                Permission check demo
            </h2>
            <ul class="space-y-1 text-sm">
                <li>
                    <span class="font-mono text-slate-600">attendance.view</span> →
                    <span :class="auth.can('attendance.view') ? 'text-emerald-700' : 'text-rose-700'">
                        {{ auth.can('attendance.view') ? 'allowed' : 'denied' }}
                    </span>
                </li>
                <li>
                    <span class="font-mono text-slate-600">role.manage</span> →
                    <span :class="auth.can('role.manage') ? 'text-emerald-700' : 'text-rose-700'">
                        {{ auth.can('role.manage') ? 'allowed' : 'denied' }}
                    </span>
                </li>
                <li>
                    <span class="font-mono text-slate-600">system.manage</span> →
                    <span :class="auth.can('system.manage') ? 'text-emerald-700' : 'text-rose-700'">
                        {{ auth.can('system.manage') ? 'allowed' : 'denied' }}
                    </span>
                </li>
            </ul>
            <p class="mt-3 text-xs text-slate-500">
                Module screens land here in Phases 5–8. The shell, router, store, HTTP client,
                and authorization are wired so each module can be added in isolation.
            </p>
        </section>
    </div>
</template>
