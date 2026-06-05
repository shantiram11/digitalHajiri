<script setup lang="ts">
import { useRouter } from 'vue-router';
const router = useRouter();
</script>

<template>
    <div class="flex min-h-screen items-center justify-center p-6">
        <div class="max-w-md rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <h1 class="mb-2 text-xl font-semibold">Permission required</h1>
            <p class="mb-4 text-sm text-slate-500">
                Your role does not grant access to this screen. If you need it, ask your organization
                administrator.
            </p>
            <button
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                @click="router.replace({ name: 'dashboard' })"
            >
                Back to dashboard
            </button>
        </div>
    </div>
</template>
