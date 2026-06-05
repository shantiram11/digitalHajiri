<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Identity\Models\Role;
use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source of truth for "this user belongs to this organization".
 *
 * `users.organization_id` is only a default pointer for SPA login UX. Every
 * authorization decision consults this table via OrganizationContext +
 * EnsureOrganizationContext middleware.
 *
 * See docs/authorization.md §9.
 */
class OrganizationMembership extends Model
{
    use Auditable;

    protected $table = 'organization_user';

    protected $fillable = [
        'organization_id', 'user_id', 'status', 'joined_at',
        'invited_by_user_id', 'default_role_id', 'metadata',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'metadata'  => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function defaultRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'default_role_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
