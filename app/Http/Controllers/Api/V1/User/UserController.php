<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\PermissionResolver;
use App\Domain\Organization\Models\OrganizationMembership;
use App\Domain\Organization\Services\OrganizationContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

final class UserController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly PermissionResolver $resolver,
    ) {}

    /**
     * Members of the current organization.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('user.view');
        $org = $this->context->require();

        $members = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('organization_id', $org->id))
            ->with(['memberships' => fn ($q) => $q->where('organization_id', $org->id)->with('defaultRole:id,slug,name')])
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json(['data' => $members]);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('user.view');
        $org = $this->context->require();

        $user = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('organization_id', $org->id))
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'user'        => $user->only(['id', 'name', 'email', 'phone', 'status']),
                'membership'  => $user->memberships()->where('organization_id', $org->id)->first(),
                'roles'       => $user->roles()->where('roles.organization_id', $org->id)->get(['id', 'slug', 'name']),
                'permissions' => $this->resolver->permissionsFor($user, $org),
            ],
        ]);
    }

    /**
     * Suspend or reactivate a member in the current org.
     */
    public function setStatus(Request $request, int $id): JsonResponse
    {
        $this->authorize('user.manage');
        $org = $this->context->require();

        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
        ]);

        if ($id === $request->user()->id) {
            abort(422, 'You cannot change your own membership status.');
        }

        $membership = OrganizationMembership::query()
            ->where('organization_id', $org->id)
            ->where('user_id', $id)
            ->firstOrFail();
        $membership->status = $data['status'];
        $membership->save();

        $this->resolver->flush($id, $org->id);

        return response()->json(['data' => $membership]);
    }

    /**
     * Remove a user from this organization (does not delete the User row).
     */
    public function removeFromOrganization(Request $request, int $id): JsonResponse
    {
        $this->authorize('user.manage');
        $org = $this->context->require();

        if ($id === $request->user()->id) {
            abort(422, 'You cannot remove yourself.');
        }

        $user = User::query()->findOrFail($id);

        // Detach all org roles in this tenant.
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        foreach ($user->roles()->where('roles.organization_id', $org->id)->get() as $role) {
            $user->removeRole($role);
        }

        OrganizationMembership::query()
            ->where('organization_id', $org->id)
            ->where('user_id', $id)
            ->delete();

        $this->resolver->flush($user->id, $org->id);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * Assign or remove an org role on a member.
     */
    public function assignRole(Request $request, int $id): JsonResponse
    {
        $this->authorize('role.manage');
        $org = $this->context->require();

        $data = $request->validate([
            'role_id' => ['required', 'integer'],
        ]);

        $user = User::query()->findOrFail($id);
        if (! $user->memberships()->where('organization_id', $org->id)->exists()) {
            abort(422, 'Target user is not a member of this organization.');
        }

        $role = Role::query()->where('organization_id', $org->id)->findOrFail($data['role_id']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $this->resolver->flush($user->id, $org->id);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function removeRole(Request $request, int $id, int $roleId): JsonResponse
    {
        $this->authorize('role.manage');
        $org = $this->context->require();

        $user = User::query()->findOrFail($id);
        $role = Role::query()->where('organization_id', $org->id)->findOrFail($roleId);

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->removeRole($role);

        $this->resolver->flush($user->id, $org->id);

        return response()->json(['data' => ['ok' => true]]);
    }
}
