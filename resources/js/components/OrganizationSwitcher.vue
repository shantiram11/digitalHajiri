<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/modules/auth/stores/auth';

const auth = useAuthStore();

const activeId = computed({
    get: () => auth.activeOrganizationId,
    set: async (value) => {
        if (value === null || value === auth.activeOrganizationId) return;
        await auth.selectOrganization(value);
    },
});
</script>

<template>
    <div v-if="auth.memberships.length > 1" class="flex items-center gap-2">
        <label for="org-switcher" class="text-sm text-slate-500">Org</label>
        <select
            id="org-switcher"
            v-model="activeId"
            class="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm focus:border-slate-900 focus:outline-none"
        >
            <option
                v-for="m in auth.memberships"
                :key="m.organization_id"
                :value="m.organization_id"
            >
                {{ m.organization_name }}
            </option>
        </select>
    </div>
    <div v-else-if="auth.activeMembership" class="text-sm text-slate-600">
        {{ auth.activeMembership.organization_name }}
    </div>
</template>
