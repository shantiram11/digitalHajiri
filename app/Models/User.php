<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationMembership;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['organization_id', 'name', 'email', 'phone', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasRoles;
    use \Illuminate\Auth\MustVerifyEmail;

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'last_login_at'          => 'datetime',
            'locked_until'           => 'datetime',
            'password_changed_at'    => 'datetime',
            'force_password_change'  => 'bool',
        ];
    }

    // ─── Default organization (users.organization_id) ──────────────────────

    /**
     * The user's default organization — used by the SPA on login when no
     * explicit org is selected. NOT the authority on membership.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // ─── Membership (authority) ────────────────────────────────────────────

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->withPivot(['status', 'joined_at', 'invited_by_user_id', 'default_role_id'])
            ->withTimestamps();
    }

    public function activeOrganizations(): BelongsToMany
    {
        return $this->organizations()->wherePivot('status', 'active');
    }

    public function isMemberOf(Organization|int $organization): bool
    {
        $id = is_int($organization) ? $organization : $organization->getKey();
        return $this->memberships()
            ->where('organization_id', $id)
            ->where('status', 'active')
            ->exists();
    }

    // ─── System role helpers ───────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->hasSystemRole('super-admin');
    }

    /**
     * Check a SYSTEM role (organization_id = null on the role).
     * For org-scoped role checks, set Spatie's team context first.
     */
    public function hasSystemRole(string $slug): bool
    {
        return $this->roles()
            ->whereNull('roles.organization_id')
            ->where('roles.slug', $slug)
            ->exists();
    }

    // ─── Lockout helpers ───────────────────────────────────────────────────

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isActiveAccount(): bool
    {
        return $this->status === 'active' && ! $this->isLocked();
    }
}
