<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Organization\Models\Organization;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Tenant-aware role.
 *
 * `organization_id` is Spatie's team_foreign_key (configured in
 * config/permission.php → `team_foreign_key: organization_id`).
 *
 * organization_id = null  → system role, applies across all tenants.
 * organization_id = value → role scoped to that organization.
 */
class Role extends SpatieRole
{
    use Auditable;

    protected $fillable = [
        'organization_id', 'name', 'guard_name', 'slug', 'description', 'is_system',
    ];

    protected $casts = [
        'is_system' => 'bool',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isSystem(): bool
    {
        return $this->is_system || $this->organization_id === null;
    }

    public function isOrganizationRole(): bool
    {
        return ! $this->isSystem();
    }
}
