<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\OrganizationInvitation;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Notifications\OrganizationInvitationNotification;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Send / accept / revoke organization invitations.
 * Flow documented in docs/authorization.md §10.
 */
final class InvitationService
{
    public const DEFAULT_TTL_DAYS = 7;

    public function send(
        Organization $organization,
        string $email,
        Role $role,
        User $invitedBy,
    ): OrganizationInvitation {
        $this->assertRoleBelongsToOrganization($role, $organization);

        // Revoke any prior pending invitation for this email/org pair so the
        // accept flow can never resolve to an out-of-date role.
        OrganizationInvitation::query()
            ->where('organization_id', $organization->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['revoked_at' => now(), 'revoked_by_user_id' => $invitedBy->id]);

        $invitation = OrganizationInvitation::query()->create([
            'organization_id'    => $organization->id,
            'invited_by_user_id' => $invitedBy->id,
            'email'              => strtolower($email),
            'role_id'            => $role->id,
            'token'              => $this->generateToken(),
            'expires_at'         => now()->addDays(self::DEFAULT_TTL_DAYS),
        ]);

        // Notify the invitee. Mail driver is `log` in local; production swap
        // is handled by env config, not code change.
        \Illuminate\Support\Facades\Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation));

        return $invitation;
    }

    /**
     * Accept an invitation by token.
     *
     * If the invitee already has an account (matched by email), they are
     * simply added as a member. If they don't, a User is created with the
     * provided password.
     */
    public function accept(string $token, ?string $password = null, ?string $name = null): OrganizationMembership
    {
        return DB::transaction(function () use ($token, $password, $name): OrganizationMembership {
            /** @var OrganizationInvitation|null $invitation */
            $invitation = OrganizationInvitation::query()->where('token', $token)->lockForUpdate()->first();

            if ($invitation === null) {
                throw new RuntimeException('Invalid invitation token.');
            }
            if ($invitation->isRevoked()) {
                throw new RuntimeException('This invitation has been revoked.');
            }
            if ($invitation->isAccepted()) {
                throw new RuntimeException('This invitation has already been accepted.');
            }
            if ($invitation->isExpired()) {
                throw new RuntimeException('This invitation has expired.');
            }

            $user = User::query()->where('email', $invitation->email)->first();
            if ($user === null) {
                if ($password === null || $name === null) {
                    throw new RuntimeException('New users must supply a name and password to accept.');
                }
                $user = User::query()->create([
                    'organization_id' => $invitation->organization_id,
                    'name'            => $name,
                    'email'           => $invitation->email,
                    'password'        => Hash::make($password),
                    'status'          => 'active',
                ]);
            }

            $membership = OrganizationMembership::query()->updateOrCreate(
                ['organization_id' => $invitation->organization_id, 'user_id' => $user->id],
                [
                    'status'             => 'active',
                    'joined_at'          => now(),
                    'invited_by_user_id' => $invitation->invited_by_user_id,
                    'default_role_id'    => $invitation->role_id,
                ],
            );

            if ($invitation->role_id !== null) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($invitation->organization_id);
                $role = Role::query()->findOrFail($invitation->role_id);
                $user->assignRole($role);
            }

            $invitation->forceFill([
                'accepted_at'      => now(),
                'accepted_user_id' => $user->id,
            ])->save();

            return $membership;
        });
    }

    public function revoke(OrganizationInvitation $invitation, User $revokedBy): void
    {
        if ($invitation->isAccepted()) {
            throw new RuntimeException('Cannot revoke an already-accepted invitation.');
        }
        $invitation->forceFill([
            'revoked_at'         => now(),
            'revoked_by_user_id' => $revokedBy->id,
        ])->save();
    }

    private function assertRoleBelongsToOrganization(Role $role, Organization $organization): void
    {
        if ($role->organization_id !== $organization->id) {
            throw new RuntimeException("Role [{$role->slug}] does not belong to organization [{$organization->id}].");
        }
    }

    private function generateToken(): string
    {
        // 64 chars, URL-safe.
        return substr(rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '='), 0, 64);
    }
}
