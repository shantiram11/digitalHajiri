import { computed } from 'vue';
import { useAuthStore } from '@/modules/auth/stores/auth';

/**
 * Permission helpers for templates and composables.
 *
 *   const { can, canAny, canAll } = usePermissions();
 *   if (can('leave.approve')) { ... }
 *
 * The check is UX-only. The server is the trust boundary; this helper just
 * decides whether to render a button. Tampering with the store is allowed
 * to show buttons, but the server will return 403.
 */
export function usePermissions() {
    const auth = useAuthStore();

    return {
        can: (perm: string): boolean => auth.can(perm),
        canAny: (perms: string[]): boolean => auth.canAny(perms),
        canAll: (perms: string[]): boolean => auth.canAll(perms),
        isSuperAdmin: computed(() => auth.isSuperAdmin),
        permissions: computed(() => auth.permissions),
    };
}
