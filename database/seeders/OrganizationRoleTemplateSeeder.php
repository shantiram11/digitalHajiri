<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Clone the org-role templates into every existing organization.
 *
 * Idempotent — updateOrCreate on (organization_id, slug). Safe to re-run
 * after adding new roles to the template. NOTE: removing a permission from
 * the template will be reflected on the next run (syncPermissions).
 *
 * In production, OrganizationProvisioner calls into the same logic when a
 * new organization is created so seeding here is only the initial bootstrap.
 */
final class OrganizationRoleTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = require database_path('seeders/data/organization_roles.php');

        Organization::query()->each(function (Organization $org) use ($templates): void {
            foreach ($templates as $tpl) {
                /** @var Role $role */
                $role = Role::query()->updateOrCreate(
                    [
                        'organization_id' => $org->id,
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
}
