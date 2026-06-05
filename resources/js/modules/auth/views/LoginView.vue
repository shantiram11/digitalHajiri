<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/modules/auth/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const email = ref('');
const password = ref('');
const error = ref<string | null>(null);
const submitting = ref(false);

async function submit(): Promise<void> {
    submitting.value = true;
    error.value = null;
    try {
        await auth.login(email.value, password.value);
        await router.push({ name: 'dashboard' });
    } catch (problem: unknown) {
        const message = (problem as { detail?: string; title?: string }).detail
            ?? (problem as { title?: string }).title
            ?? 'Login failed';
        error.value = message;
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center p-6">
        <form
            class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-sm"
            @submit.prevent="submit"
        >
            <h1 class="mb-1 text-2xl font-semibold">e-Hajiri</h1>
            <p class="mb-6 text-sm text-slate-500">Sign in to continue.</p>

            <label class="mb-3 block">
                <span class="mb-1 block text-sm font-medium">Email</span>
                <input
                    v-model="email"
                    type="email"
                    required
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none"
                />
            </label>

            <label class="mb-4 block">
                <span class="mb-1 block text-sm font-medium">Password</span>
                <input
                    v-model="password"
                    type="password"
                    required
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none"
                />
            </label>

            <button
                type="submit"
                :disabled="submitting"
                class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
            >
                {{ submitting ? 'Signing in…' : 'Sign in' }}
            </button>

            <p v-if="error" class="mt-3 text-sm text-rose-600">{{ error }}</p>
        </form>
    </div>
</template>
