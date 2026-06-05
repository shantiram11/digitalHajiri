<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\PermissionResolver;
use App\Domain\Organization\Services\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Identity\Helpers\IdentityTestHelpers;
use Tests\TestCase;

final class PermissionResolverTest extends TestCase
{
    use RefreshDatabase, IdentityTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedIdentity();
    }

    public function test_org_admin_has_their_role_permissions_in_their_org(): void
    {
        $org   = $this->makeOrganization('Muni A');
        $admin = $this->makeUserIn($org, 'organization-admin');
        app(OrganizationContext::class)->set($org);

        $resolver = app(PermissionResolver::class);
        $this->assertTrue($resolver->allows($admin, 'leave.approve'));
        $this->assertTrue($resolver->allows($admin, 'device.manage'));
    }

    public function test_employee_lacks_management_permissions(): void
    {
        $org      = $this->makeOrganization();
        $employee = $this->makeUserIn($org, 'employee');
        app(OrganizationContext::class)->set($org);

        $resolver = app(PermissionResolver::class);
        $this->assertTrue($resolver->allows($employee, 'leave.create'));
        $this->assertFalse($resolver->allows($employee, 'leave.approve'));
        $this->assertFalse($resolver->allows($employee, 'device.manage'));
    }

    public function test_direct_user_permission_overrides_role(): void
    {
        $org      = $this->makeOrganization();
        $employee = $this->makeUserIn($org, 'employee');
        app(OrganizationContext::class)->set($org);

        $resolver = app(PermissionResolver::class);
        $this->assertFalse($resolver->allows($employee, 'leave.approve'));

        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $employee->givePermissionTo('leave.approve');
        $resolver->flush($employee->id, $org->id);

        $this->assertTrue($resolver->allows($employee, 'leave.approve'));
    }

    public function test_user_denied_in_organization_they_are_not_a_member_of(): void
    {
        $orgA  = $this->makeOrganization('A');
        $orgB  = $this->makeOrganization('B');
        $admin = $this->makeUserIn($orgA, 'organization-admin');

        app(OrganizationContext::class)->set($orgB);
        $this->assertFalse(app(PermissionResolver::class)->allows($admin, 'leave.approve'));
    }

    public function test_system_role_grants_apply_across_organizations(): void
    {
        $orgA  = $this->makeOrganization('A');
        $super = $this->makeSuperAdmin();

        app(OrganizationContext::class)->set($orgA);
        $this->assertTrue(app(PermissionResolver::class)->allows($super, 'leave.approve'));
        $this->assertTrue(app(PermissionResolver::class)->allows($super, 'system.manage'));
    }

    public function test_suspended_account_loses_all_permissions(): void
    {
        $org   = $this->makeOrganization();
        $admin = $this->makeUserIn($org, 'organization-admin');
        app(OrganizationContext::class)->set($org);

        $resolver = app(PermissionResolver::class);
        $this->assertTrue($resolver->allows($admin, 'leave.approve'));

        $admin->forceFill(['status' => 'suspended'])->save();
        $admin->refresh();
        $resolver->flush($admin->id, $org->id);

        $this->assertFalse($resolver->allows($admin, 'leave.approve'));
    }

    public function test_user_can_helper_uses_gate_before(): void
    {
        // Gate::before is wired in AuthServiceProvider — verify the user model
        // helper resolves through it.
        $org      = $this->makeOrganization();
        $admin    = $this->makeUserIn($org, 'organization-admin');
        $employee = $this->makeUserIn($org, 'employee');
        app(OrganizationContext::class)->set($org);

        $this->assertTrue($admin->can('leave.approve'));
        $this->assertFalse($employee->can('leave.approve'));
    }
}
