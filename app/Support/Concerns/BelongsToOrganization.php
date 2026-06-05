<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Domain\Organization\Models\Organization;
use App\Support\Eloquent\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attach to any model whose rows carry organization_id.
 *
 * Applies an automatic global scope so queries are tenant-isolated whenever
 * an organization context is active. The scope is bypassable with
 * `withoutGlobalScope(OrganizationScope::class)` for cross-tenant admin work.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if ($model->organization_id === null
                && app()->bound('current_organization')
                && app('current_organization') !== null
            ) {
                $model->organization_id = app('current_organization')->id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
