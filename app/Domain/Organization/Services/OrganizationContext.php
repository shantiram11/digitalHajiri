<?php

declare(strict_types=1);

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Holds the active organization for the current request / job / console call.
 *
 *  - Bound under the container key `current_organization` for the
 *    OrganizationScope global scope on Eloquent models.
 *  - Sets Spatie's PermissionRegistrar team id so role/permission checks
 *    auto-scope to the active org without the caller threading it through.
 *
 * Resolution rules live in EnsureOrganizationContext middleware; this class
 * is intentionally storage-only.
 */
final class OrganizationContext
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
        app()->instance('current_organization', $organization);

        // Tell Spatie which "team" we're in so its built-in role/permission
        // checks scope correctly. 0 = system context (sentinel; required
        // because composite PKs reject NULL on MySQL 8+).
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization?->id ?? 0);
    }

    public function current(): ?Organization
    {
        return $this->organization;
    }

    public function require(): Organization
    {
        if ($this->organization === null) {
            throw new RuntimeException('No organization context set.');
        }
        return $this->organization;
    }

    /**
     * Resolve the org id the user is *asking* to operate inside, ordered by
     * specificity. Returns null when no preference is signalled.
     *
     * Used by EnsureOrganizationContext.
     */
    public function resolveRequestedOrgId(\Illuminate\Http\Request $request, ?User $user): ?int
    {
        // 1. Bearer-token ability `org:<id>` (integrations).
        $token = $user?->currentAccessToken();
        if ($token !== null && method_exists($token, 'abilities')) {
            foreach ((array) $token->abilities() as $ability) {
                if (str_starts_with((string) $ability, 'org:')) {
                    return (int) substr((string) $ability, 4);
                }
            }
        }

        // 2. Explicit request header.
        $header = $request->header('X-Organization-Id');
        if ($header !== null && is_numeric($header)) {
            return (int) $header;
        }

        // 3. Session attribute (set by /api/v1/auth/select-organization).
        if ($request->hasSession()) {
            $session = $request->session()->get('current_organization_id');
            if ($session !== null) {
                return (int) $session;
            }
        }

        // 4. Default org pointer on the user row.
        return $user?->organization_id;
    }
}
