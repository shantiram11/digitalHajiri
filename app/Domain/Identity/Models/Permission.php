<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Concerns\Auditable;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission catalog. Global (not tenant-scoped) — see docs/authorization.md §4.
 *
 * The `module` and `description` columns are our additions used to render the
 * permission management UI and to group permissions in reports.
 */
class Permission extends SpatiePermission
{
    use Auditable;

    protected $fillable = ['name', 'guard_name', 'module', 'description'];
}
