<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Permission;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Services\PermissionResolver;
use App\Domain\Organization\Services\OrganizationContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

final class PermissionController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly PermissionResolver $resolver,
    ) {}

    /**
     * The full permission catalog.
     */
    public function index(): JsonResponse
    {
        $this->authorize('permission.view');

        return response()->json([
            'data' => Permission::query()
                ->orderBy('module')->orderBy('name')
                ->get(['id', 'name', 'module', 'description']),
        ]);
    }

    /**
     * Direct (non-role) permissions a user has in the current org.
     */
    public function userPermissions(int $userId): JsonResponse
    {
        $this->authorize('permission.view');
        $org = $this->context->require();

        $user = User::query()->findOrFail($userId);

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);

        return response()->json([
            'data' => [
                'direct'    => $user->permissions()->wherePivot('organization_id', $org->id)->pluck('name'),
                'effective' => $this->resolver->permissionsFor($user, $org),
            ],
        ]);
    }

    /**
     * Grant a direct permission to a user in the current org.
     */
    public function grant(Request $request, int $userId): JsonResponse
    {
        $this->authorize('permission.assign');
        $org = $this->context->require();

        $data = $request->validate([
            'permission' => ['required', 'string'],
        ]);

        $user = User::query()->findOrFail($userId);

        if (! $user->memberships()->where('organization_id', $org->id)->exists()) {
            abort(422, 'Target user is not a member of this organization.');
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->givePermissionTo($data['permission']);

        $this->resolver->flush($user->id, $org->id);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function revoke(Request $request, int $userId): JsonResponse
    {
        $this->authorize('permission.assign');
        $org = $this->context->require();

        $data = $request->validate([
            'permission' => ['required', 'string'],
        ]);

        $user = User::query()->findOrFail($userId);

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->revokePermissionTo($data['permission']);

        $this->resolver->flush($user->id, $org->id);

        return response()->json(['data' => ['ok' => true]]);
    }
}
