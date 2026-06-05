<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Role;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Services\OrganizationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

final class RoleController extends Controller
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('role.view');
        $org = $this->context->require();

        $roles = Role::query()
            ->where('organization_id', $org->id)
            ->withCount('permissions')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $roles]);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('role.view');
        $org = $this->context->require();

        $role = Role::query()
            ->where('organization_id', $org->id)
            ->with('permissions:id,name,module')
            ->findOrFail($id);

        return response()->json(['data' => $role]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('role.manage');
        $org = $this->context->require();

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:255'],
            'permissions'   => ['array'],
            'permissions.*' => ['string'],
        ]);

        $role = Role::query()->create([
            'organization_id' => $org->id,
            'name'            => $data['name'],
            'slug'            => Str::slug($data['name']),
            'guard_name'      => 'web',
            'description'     => $data['description'] ?? null,
            'is_system'       => false,
        ]);

        if (! empty($data['permissions'])) {
            $validPerms = Permission::query()
                ->whereIn('name', $data['permissions'])
                ->pluck('name')
                ->all();
            $role->syncPermissions($validPerms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['data' => $role->fresh('permissions')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('role.manage');
        $org = $this->context->require();

        $role = Role::query()->where('organization_id', $org->id)->findOrFail($id);

        if ($role->is_system) {
            abort(422, 'System roles cannot be edited.');
        }

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:255'],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);

        if (isset($data['name'])) {
            $role->name = $data['name'];
            $role->slug = Str::slug($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $role->description = $data['description'];
        }
        $role->save();

        if (array_key_exists('permissions', $data)) {
            $validPerms = Permission::query()->whereIn('name', $data['permissions'])->pluck('name')->all();
            $role->syncPermissions($validPerms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['data' => $role->fresh('permissions')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('role.manage');
        $org = $this->context->require();

        $role = Role::query()->where('organization_id', $org->id)->findOrFail($id);

        if ($role->is_system) {
            abort(422, 'System roles cannot be deleted.');
        }

        $role->delete();
        return response()->json(['data' => ['ok' => true]]);
    }
}
