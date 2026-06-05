<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Helpers;

use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationMembership;
use App\Domain\Organization\Services\OrganizationProvisioner;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

trait IdentityTestHelpers
{
    protected function seedIdentity(): void
    {
        $this->seed([
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\SystemRoleSeeder::class,
        ]);
    }

    protected function makeOrganization(string $name = 'Test Org'): Organization
    {
        $org = Organization::create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name . '-' . uniqid()),
        ]);
        app(OrganizationProvisioner::class)->seedDefaultRoles($org);
        return $org;
    }

    protected function makeUserIn(
        Organization $organization,
        string $roleSlug = 'employee',
        array $attrs = [],
    ): User {
        $user = User::create(array_merge([
            'organization_id' => $organization->id,
            'name'            => 'Member ' . uniqid(),
            'email'           => 'u' . uniqid() . '@test.np',
            'password'        => Hash::make('test-password-1234'),
            'status'          => 'active',
        ], $attrs));

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id'         => $user->id,
            'status'          => 'active',
            'joined_at'       => now(),
        ]);

        $role = Role::query()
            ->where('organization_id', $organization->id)
            ->where('slug', $roleSlug)
            ->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $user->assignRole($role);

        return $user;
    }

    protected function makeSuperAdmin(array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name'     => 'Super Admin',
            'email'    => 'super' . uniqid() . '@test.np',
            'password' => Hash::make('test-password-1234'),
            'status'   => 'active',
        ], $attrs));

        $role = Role::query()->whereNull('organization_id')->where('slug', 'super-admin')->firstOrFail();
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);
        $user->assignRole($role);
        return $user;
    }
}
