/**
 * v-can directive.
 *
 *   <button v-can="'leave.approve'">…</button>
 *   <button v-can.any="['leave.approve', 'leave.manage-balances']">…</button>
 *   <button v-can.all="['role.view', 'role.manage']">…</button>
 *
 * Hides the element (display:none) when the user lacks the required
 * permission(s). Server-side checks remain authoritative.
 */
import type { Directive, DirectiveBinding } from 'vue';
import { useAuthStore } from '@/modules/auth/stores/auth';

function check(binding: DirectiveBinding): boolean {
    const auth = useAuthStore();
    if (auth.isSuperAdmin) return true;

    if (binding.modifiers.any) {
        return auth.canAny(binding.value as string[]);
    }
    if (binding.modifiers.all) {
        return auth.canAll(binding.value as string[]);
    }
    return auth.can(binding.value as string);
}

export const canDirective: Directive = {
    mounted(el, binding) {
        if (!check(binding)) {
            (el as HTMLElement).style.display = 'none';
        }
    },
    updated(el, binding) {
        (el as HTMLElement).style.display = check(binding) ? '' : 'none';
    },
};
