<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

final class SystemRoleSeeder extends Seeder
{
    public function run(): void
    {
        $entries = require database_path('seeders/data/system_roles.php');

        foreach ($entries as $entry) {
            /** @var Role $role */
            $role = Role::query()->updateOrCreate(
                [
                    'organization_id' => null,
                    'slug'            => $entry['slug'],
                    'guard_name'      => 'web',
                ],
                [
                    'name'        => $entry['name'],
                    'description' => $entry['description'],
                    'is_system'   => true,
                ],
            );

            $perms = $entry['permissions'] === '__ALL__'
                ? Permission::query()->pluck('name')->all()
                : (array) $entry['permissions'];

            // Spatie's syncPermissions writes role_has_permissions; team scoping
            // is irrelevant here because that pivot is global.
            $role->syncPermissions($perms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
