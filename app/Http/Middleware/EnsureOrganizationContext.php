<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Services\OrganizationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves and validates the active organization for the request.
 *
 *  - Resolution order (docs/authentication.md §6.2):
 *      bearer ability  →  X-Organization-Id  →  session  →  users.organization_id
 *  - Membership check: rejects 409 if the user is not an active member.
 *  - Super admins bypass the membership check; their access is gated by
 *    system.* permissions and logged.
 *  - Anonymous requests are passed through (some auth endpoints have no
 *    organization context yet).
 */
final class EnsureOrganizationContext
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $orgId = $this->context->resolveRequestedOrgId($request, $user);

        if ($orgId === null) {
            $this->context->set(null);
            return $next($request);
        }

        $organization = Organization::query()->find($orgId);
        if ($organization === null) {
            return $this->error('Organization not found.', 404);
        }

        $isSuperAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

        if (! $isSuperAdmin) {
            $isMember = $user->memberships()
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->exists();

            if (! $isMember) {
                return $this->error('You are not a member of the requested organization.', 409);
            }
        }

        $this->context->set($organization);

        return $next($request);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'type'   => 'organization-context',
            'title'  => 'Organization context error',
            'status' => $status,
            'detail' => $message,
        ], $status);
    }
}
