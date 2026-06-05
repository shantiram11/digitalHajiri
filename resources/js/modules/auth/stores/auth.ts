/**
 * Auth + identity state for the SPA.
 *
 * Server-set HttpOnly session cookie holds the credentials; the store only
 * remembers user identity, memberships, and permissions for UX (route guards,
 * navigation filtering, button hiding). Every server request is independently
 * authorized — the store is never a security boundary.
 */
import { defineStore } from 'pinia';
import { http } from '@/services/http';

export interface MembershipSummary {
    organization_id: number;
    organization_name: string;
    organization_slug: string;
    default_role: string | null;
    status: string;
}

export interface IdentityUser {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    organization_id: number | null;
    force_password_change: boolean;
}

interface MeResponse {
    user: IdentityUser;
    memberships: MembershipSummary[];
    system_roles: string[];
    active_organization_id: number | null;
    is_super_admin: boolean;
}

interface State {
    user: IdentityUser | null;
    memberships: MembershipSummary[];
    systemRoles: string[];
    activeOrganizationId: number | null;
    isSuperAdmin: boolean;
    /** Permission strings effective in the active organization. */
    permissions: Set<string>;
    bootstrapped: boolean;
}

export const useAuthStore = defineStore('auth', {
    state: (): State => ({
        user: null,
        memberships: [],
        systemRoles: [],
        activeOrganizationId: null,
        isSuperAdmin: false,
        permissions: new Set(),
        bootstrapped: false,
    }),

    getters: {
        isAuthenticated: (s) => s.user !== null,
        activeMembership: (s): MembershipSummary | null =>
            s.memberships.find((m) => m.organization_id === s.activeOrganizationId) ?? null,
        can:
            (s) =>
            (perm: string): boolean =>
                s.isSuperAdmin || s.permissions.has(perm),
        canAny:
            (s) =>
            (perms: string[]): boolean =>
                s.isSuperAdmin || perms.some((p) => s.permissions.has(p)),
        canAll:
            (s) =>
            (perms: string[]): boolean =>
                s.isSuperAdmin || perms.every((p) => s.permissions.has(p)),
    },

    actions: {
        async login(email: string, password: string): Promise<void> {
            await http.get('/sanctum/csrf-cookie', { baseURL: '' });
            await http.post('/auth/login', { email, password });
            await this.hydrate();
        },

        async hydrate(): Promise<void> {
            try {
                const { data } = await http.get<{ data: MeResponse }>('/auth/me');
                this.setIdentity(data.data);
                await this.refreshPermissions();
                this.bootstrapped = true;
            } catch {
                this.reset();
                this.bootstrapped = true;
            }
        },

        async refreshPermissions(): Promise<void> {
            if (this.user === null || this.activeOrganizationId === null) {
                this.permissions = new Set();
                return;
            }
            const { data } = await http.get<{
                data: { direct: string[]; effective: string[] };
            }>(`/users/${this.user.id}/permissions`);
            this.permissions = new Set(data.data.effective);
        },

        async selectOrganization(orgId: number): Promise<void> {
            await http.post('/auth/select-organization', { organization_id: orgId });
            this.activeOrganizationId = orgId;
            await this.refreshPermissions();
        },

        async logout(): Promise<void> {
            try {
                await http.post('/auth/logout');
            } finally {
                this.reset();
            }
        },

        setIdentity(me: MeResponse): void {
            this.user = me.user;
            this.memberships = me.memberships;
            this.systemRoles = me.system_roles;
            this.activeOrganizationId = me.active_organization_id;
            this.isSuperAdmin = me.is_super_admin;
        },

        reset(): void {
            this.user = null;
            this.memberships = [];
            this.systemRoles = [];
            this.activeOrganizationId = null;
            this.isSuperAdmin = false;
            this.permissions = new Set();
        },
    },
});
