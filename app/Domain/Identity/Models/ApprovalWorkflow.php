<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'name', 'target_type', 'steps', 'is_default', 'is_active',
    ];

    protected $casts = [
        'steps'      => 'array',
        'is_default' => 'bool',
        'is_active'  => 'bool',
    ];
}
