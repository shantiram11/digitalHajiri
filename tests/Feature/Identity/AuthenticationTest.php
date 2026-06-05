<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Identity\Helpers\IdentityTestHelpers;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase, IdentityTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedIdentity();
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $org   = $this->makeOrganization();
        $admin = $this->makeUserIn($org, 'organization-admin', [
            'email'    => 'admin@test.np',
            'password' => Hash::make('test-password-1234'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin@test.np',
            'password' => 'test-password-1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $admin->id)
            ->assertJsonPath('data.user.email', 'admin@test.np');

        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $org = $this->makeOrganization();
        $this->makeUserIn($org, 'employee', [
            'email'    => 'user@test.np',
            'password' => Hash::make('correct-password-12345'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@test.np',
            'password' => 'wrong-password-12345',
        ])->assertStatus(422);
    }

    public function test_login_fails_for_suspended_account(): void
    {
        $org  = $this->makeOrganization();
        $user = $this->makeUserIn($org, 'employee', [
            'email'    => 'suspended@test.np',
            'password' => Hash::make('test-password-1234'),
            'status'   => 'suspended',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'suspended@test.np',
            'password' => 'test-password-1234',
        ])->assertStatus(422);
    }

    public function test_login_failed_attempts_lock_account(): void
    {
        $org  = $this->makeOrganization();
        $user = $this->makeUserIn($org, 'employee', [
            'email'    => 'lockme@test.np',
            'password' => Hash::make('correct-password-12345'),
        ]);

        // Manually push the user to 9 failed attempts; one more should lock.
        $user->forceFill(['failed_login_attempts' => 9])->save();

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'lockme@test.np',
            'password' => 'wrong',
        ])->assertStatus(422);

        $this->assertNotNull($user->fresh()->locked_until);
    }

    public function test_authenticated_me_endpoint_returns_memberships(): void
    {
        $org  = $this->makeOrganization('Demo Muni');
        $user = $this->makeUserIn($org, 'organization-admin');

        $this->actingAs($user, 'web')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.memberships.0.organization_id', $org->id)
            ->assertJsonPath('data.memberships.0.organization_name', 'Demo Muni');
    }

    public function test_logout_invalidates_session(): void
    {
        $org  = $this->makeOrganization();
        $user = $this->makeUserIn($org, 'employee');

        $this->actingAs($user, 'web')
            ->postJson('/api/v1/auth/logout')
            ->assertOk();
    }
}
