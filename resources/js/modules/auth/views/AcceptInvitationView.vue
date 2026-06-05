<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { http } from '@/services/http';

const route = useRoute();
const router = useRouter();

interface InvitationDetails {
    organization: { id: number; name: string };
    role: string | null;
    email: string;
    expires_at: string;
}

const invitation = ref<InvitationDetails | null>(null);
const error = ref<string | null>(null);
const loading = ref(true);
const submitting = ref(false);

const name = ref('');
const password = ref('');

const token = (route.query.token as string) ?? '';

onMounted(async () => {
    if (!token) {
        error.value = 'Missing invitation token.';
        loading.value = false;
        return;
    }
    try {
        const { data } = await http.get<{ data: InvitationDetails }>(
            `/invitations/${encodeURIComponent(token)}`,
        );
        invitation.value = data.data;
    } catch (e: unknown) {
        error.value = (e as { detail?: string; title?: string }).detail
            ?? (e as { title?: string }).title
            ?? 'Invitation not found or no longer valid.';
    } finally {
        loading.value = false;
    }
});

async function accept(): Promise<void> {
    submitting.value = true;
    error.value = null;
    try {
        await http.post('/invitations/accept', {
            token,
            name: name.value || undefined,
            password: password.value || undefined,
        });
        await router.replace({ name: 'login' });
    } catch (e: unknown) {
        error.value = (e as { detail?: string; title?: string }).detail
            ?? (e as { title?: string }).title
            ?? 'Could not accept invitation.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center p-6">
        <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="mb-2 text-xl font-semibold">Accept invitation</h1>

            <div v-if="loading" class="text-sm text-slate-500">Loading…</div>
            <div v-else-if="error" class="text-sm text-rose-600">{{ error }}</div>
            <form v-else-if="invitation" class="mt-4 space-y-4" @submit.prevent="accept">
                <p class="text-sm text-slate-600">
                    You're invited to join
                    <strong>{{ invitation.organization.name }}</strong>
                    <span v-if="invitation.role"> as <strong>{{ invitation.role }}</strong></span>.
                </p>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Your name</span>
                    <input
                        v-model="name"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none"
                        placeholder="Leave blank if you already have an account"
                    />
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Set a password</span>
                    <input
                        v-model="password"
                        type="password"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none"
                        placeholder="Required for new accounts"
                    />
                </label>
                <button
                    type="submit"
                    :disabled="submitting"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                >
                    {{ submitting ? 'Working…' : 'Accept invitation' }}
                </button>
            </form>
        </div>
    </div>
</template>
