<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Identity\Helpers\IdentityTestHelpers;
use Tests\TestCase;

final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase, IdentityTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedIdentity();
    }

    public function test_non_member_gets_409_when_using_x_organization_id_header(): void
    {
        $orgA = $this->makeOrganization('A');
        $orgB = $this->makeOrganization('B');
        $userA = $this->makeUserIn($orgA, 'organization-admin');

        $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', (string) $orgB->id)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(409);
    }

    public function test_member_succeeds_with_their_own_org_header(): void
    {
        $orgA = $this->makeOrganization('A');
        $userA = $this->makeUserIn($orgA, 'organization-admin');

        $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', (string) $orgA->id)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_super_admin_bypasses_membership_check(): void
    {
        $orgA  = $this->makeOrganization('A');
        $super = $this->makeSuperAdmin();

        $this->actingAs($super, 'web')
            ->withHeader('X-Organization-Id', (string) $orgA->id)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_unknown_organization_id_returns_404(): void
    {
        $org  = $this->makeOrganization();
        $user = $this->makeUserIn($org, 'employee');

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', '99999')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(404);
    }
}
