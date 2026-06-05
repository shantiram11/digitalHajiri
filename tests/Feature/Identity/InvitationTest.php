<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Identity\Models\OrganizationInvitation;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\InvitationService;
use App\Domain\Organization\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Feature\Identity\Helpers\IdentityTestHelpers;
use Tests\TestCase;

final class InvitationTest extends TestCase
{
    use RefreshDatabase, IdentityTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedIdentity();
    }

    public function test_invitation_is_created_with_token_and_expiry(): void
    {
        $org   = $this->makeOrganization();
        $admin = $this->makeUserIn($org, 'organization-admin');
        $role  = Role::query()->where('organization_id', $org->id)->where('slug', 'employee')->firstOrFail();

        $invitation = app(InvitationService::class)->send($org, 'newbie@test.np', $role, $admin);

        $this->assertEquals('newbie@test.np', $invitation->email);
        $this->assertEquals($org->id, $invitation->organization_id);
        $this->assertEquals($role->id, $invitation->role_id);
        $this->assertNotEmpty($invitation->token);
        $this->assertTrue($invitation->expires_at->isFuture());
    }

    public function test_sending_a_new_invitation_revokes_prior_pending_one(): void
    {
        $org   = $this->makeOrganization();
        $admin = $this->makeUserIn($org, 'organization-admin');
        $role  = Role::query()->where('organization_id', $org->id)->where('slug', 'employee')->firstOrFail();

        $first  = app(InvitationService::class)->send($org, 'dup@test.np', $role, $admin);
        $second = app(InvitationService::class)->send($org, 'dup@test.np', $role, $admin);

        $this->assertNotNull($first->fresh()->revoked_at);
        $this->assertNull($second->revoked_at);
    }

    public function test_accepting_creates_user_membership_and_assigns_role(): void
    {
        $org   = $this->makeOrganization();
        $admin = $this->makeUserIn($org, 'organization-admin');
        $role  = Role::query()->where('organization_id', $org->id)->where('slug', 'attendance-manager')->firstOrFail();

        $invitation = app(InvitationService::class)->send($org, 'newbie@test.np', $role, $admin);
        $membership = app(InvitationService::class)->accept(
            token: $invitation->token,
            password: 'pw-12345678901234',
            name: 'New Newbie',
        );

        $this->assertInstanceOf(OrganizationMembership::class, $membership);
        $user = User::where('email', 'newbie@test.np')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isMemberOf($org));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_accepting_with_existing_user_links_membership(): void
    {
        $org      = $this->makeOrganization();
        $admin    = $this->makeUserIn($org, 'organization-admin');
        $existing = User::create([
            'name'     => 'Existing',
            'email'    => 'existing@test.np',
            'password' => bcrypt('test1234567890'),
            'status'   => 'active',
        ]);
        $role = Role::query()->where('organization_id', $org->id)->where('slug', 'employee')->firstOrFail();

        $invitation = app(InvitationService::class)->send($org, 'existing@test.np', $role, $admin);
        app(InvitationService::class)->accept($invitation->token);

        $this->assertTrue($existing->fresh()->isMemberOf($org));
    }

    public function test_accepting_an_expired_invitation_throws(): void
    {
        $org   = $this->makeOrganization();
        $admin = $this->makeUserIn($org, 'organization-admin');
        $role  = Role::query()->where('organization_id', $org->id)->where('slug', 'employee')->firstOrFail();

        $invitation = app(InvitationService::class)->send($org, 'newbie@test.np', $role, $admin);
        $invitation->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->expectException(RuntimeException::class);
        app(InvitationService::class)->accept($invitation->token, 'pw-12345678901234', 'X');
    }

    public function test_revoked_invitation_cannot_be_accepted(): void
    {
        $org   = $this->makeOrganization();
        $admin = $this->makeUserIn($org, 'organization-admin');
        $role  = Role::query()->where('organization_id', $org->id)->where('slug', 'employee')->firstOrFail();

        $invitation = app(InvitationService::class)->send($org, 'newbie@test.np', $role, $admin);
        app(InvitationService::class)->revoke($invitation, $admin);

        $this->expectException(RuntimeException::class);
        app(InvitationService::class)->accept($invitation->token, 'pw-12345678901234', 'X');
    }
}
