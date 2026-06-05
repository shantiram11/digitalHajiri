<?php

declare(strict_types=1);

namespace App\Domain\Organization\Services;

use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-stop service to bring a new organization into a usable state.
 *
 * Steps:
 *   1. Seed the org-role templates into the new organization.
 *   2. Optionally attach a primary admin user as an active member with the
 *      organization-admin role.
 *
 * Callers: Organization create endpoint, OrgFactory in tests,
 * OrganizationRoleTemplateSeeder for the bootstrap run.
 */
final class OrganizationProvisioner
{
    public function seedDefaultRoles(Organization $organization): void
    {
        $templates = require database_path('seeders/data/organization_roles.php');

        DB::transaction(function () use ($organization, $templates): void {
            foreach ($templates as $tpl) {
                /** @var Role $role */
                $role = Role::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'slug'            => $tpl['slug'],
                        'guard_name'      => 'web',
                    ],
                    [
                        'name'        => $tpl['name'],
                        'description' => $tpl['description'],
                        'is_system'   => false,
                    ],
                );
                $role->syncPermissions($tpl['permissions']);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function attachPrimaryAdmin(Organization $organization, User $user): OrganizationMembership
    {
        return DB::transaction(function () use ($organization, $user): OrganizationMembership {
            $adminRole = Role::query()
                ->where('organization_id', $organization->id)
                ->where('slug', 'organization-admin')
                ->firstOrFail();

            $membership = OrganizationMembership::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $user->id],
                [
                    'status'           => 'active',
                    'joined_at'        => now(),
                    'default_role_id'  => $adminRole->id,
                ],
            );

            // Use Spatie's registrar to write into model_has_roles with the right team id.
            app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
            $user->assignRole($adminRole);

            // If the user has no default org yet, pick this one for the SPA login UX.
            if ($user->organization_id === null) {
                $user->forceFill(['organization_id' => $organization->id])->save();
            }

            return $membership;
        });
    }
}
