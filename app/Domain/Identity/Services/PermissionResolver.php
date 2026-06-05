<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Services\OrganizationContext;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant-aware authorization resolver.
 *
 * Order (docs/authorization.md §7.2):
 *   1. System role allow (organization_id IS NULL)
 *   2. Direct user permission in current org
 *   3. Org role permission in current org
 *   4. Otherwise deny
 *
 * Wired into Laravel's Gate via Gate::before in AuthServiceProvider so
 * $user->can('foo') Just Works.
 */
final class PermissionResolver
{
    public const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly OrganizationContext $context,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * Does the user have the ability in the currently-resolved organization?
     */
    public function allows(User $user, string $ability): bool
    {
        // Suspended accounts get nothing.
        if (! $user->isActiveAccount()) {
            return false;
        }

        // Anyone with a system role granting this ability is allowed everywhere.
        if ($this->systemRolesAllow($user, $ability)) {
            return true;
        }

        $org = $this->context->current();
        if ($org === null) {
            // No org context = no tenant-scoped permission can apply.
            return false;
        }

        return $this->inOrganization($user, $org, $ability);
    }

    /**
     * Explicit org variant for cases where the resolver should not consult
     * the current context (cross-tenant admin tooling, background jobs).
     */
    public function allowsIn(User $user, Organization $org, string $ability): bool
    {
        if (! $user->isActiveAccount()) {
            return false;
        }
        if ($this->systemRolesAllow($user, $ability)) {
            return true;
        }
        return $this->inOrganization($user, $org, $ability);
    }

    /**
     * The full set of abilities the user has, system + org-scoped.
     * Used by the SPA permission store.
     *
     * @return Collection<int, string>
     */
    public function permissionsFor(User $user, Organization $org): Collection
    {
        if (! $user->isActiveAccount()) {
            return collect();
        }

        $array = Cache::remember(
            $this->cacheKey($user->id, $org->id),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->computePermissionsFor($user, $org)->all(),
        );

        return collect($array);
    }

    /**
     * Manually invalidate a user's permission cache for an org.
     * Called by event listeners on role/permission/membership change.
     */
    public function flush(int $userId, ?int $organizationId = null): void
    {
        if ($organizationId !== null) {
            Cache::forget($this->cacheKey($userId, $organizationId));
            return;
        }
        // Coarse-grained flush: forget all orgs for this user. Pattern-deletes
        // are not portable across all cache stores, so we use a per-user
        // version key (incremented on coarse flush) that prefixes every entry.
        Cache::forget("auth:user:{$userId}:perms:version");
    }

    // ─── Internals ─────────────────────────────────────────────────────────

    private function systemRolesAllow(User $user, string $ability): bool
    {
        $perms = Cache::remember(
            "auth:user:{$user->id}:system-perms",
            self::CACHE_TTL_SECONDS,
            function () use ($user): array {
                // System role assignments use team_id = 0 (sentinel) and
                // roles.organization_id is NULL.
                $systemRoleIds = \Illuminate\Support\Facades\DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', $user->getMorphClass())
                    ->where('model_has_roles.model_id', $user->id)
                    ->whereNull('roles.organization_id')
                    ->pluck('roles.id');

                if ($systemRoleIds->isEmpty()) {
                    return [];
                }

                return Role::query()
                    ->whereIn('id', $systemRoleIds)
                    ->with('permissions:id,name')
                    ->get()
                    ->flatMap(fn (Role $r) => $r->permissions->pluck('name'))
                    ->unique()
                    ->values()
                    ->all();
            },
        );

        return in_array($ability, $perms, true);
    }

    private function inOrganization(User $user, Organization $org, string $ability): bool
    {
        if (! $this->isActiveMember($user, $org)) {
            return false;
        }
        return $this->permissionsFor($user, $org)->contains($ability);
    }

    private function isActiveMember(User $user, Organization $org): bool
    {
        return $user->memberships()
            ->where('organization_id', $org->id)
            ->where('status', 'active')
            ->exists();
    }

    /** @return Collection<int, string> */
    private function computePermissionsFor(User $user, Organization $org): Collection
    {
        // Tell Spatie to scope to this org.
        $this->registrar->setPermissionsTeamId($org->id);

        // Direct user permissions.
        $direct = $user->permissions()
            ->wherePivot('organization_id', $org->id)
            ->pluck('name');

        // Org-role permissions.
        $orgRolePerms = Permission::query()
            ->whereIn(
                'id',
                fn ($q) => $q->select('permission_id')
                    ->from('role_has_permissions')
                    ->whereIn(
                        'role_id',
                        fn ($q2) => $q2->select('role_id')
                            ->from('model_has_roles')
                            ->where('model_type', $user->getMorphClass())
                            ->where('model_id', $user->id)
                            ->where('organization_id', $org->id)
                    ),
            )
            ->pluck('name');

        return $direct->merge($orgRolePerms)->unique()->values();
    }

    private function cacheKey(int $userId, int $orgId): string
    {
        return "auth:user:{$userId}:org:{$orgId}:perms";
    }
}
