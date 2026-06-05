<?php

declare(strict_types=1);

namespace App\Support\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that constrains queries to the current organization.
 *
 * Resolution order:
 *   1. If `current_organization` is bound in the container, use it.
 *   2. Otherwise the scope is a no-op (super-admin, console, tests with
 *      explicit org filters).
 *
 * Bypass when needed:
 *   Model::withoutGlobalScope(OrganizationScope::class)->...
 */
final class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('current_organization')) {
            return;
        }

        $organization = app('current_organization');

        if ($organization === null) {
            return;
        }

        $builder->where($model->qualifyColumn('organization_id'), $organization->id);
    }
}
