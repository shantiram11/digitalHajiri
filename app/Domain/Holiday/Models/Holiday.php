<?php

declare(strict_types=1);

namespace App\Domain\Holiday\Models;

use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'date', 'name', 'type',
        'is_recurring', 'is_paid', 'fiscal_year', 'metadata',
    ];

    protected $casts = [
        'date'         => 'date',
        'is_recurring' => 'bool',
        'is_paid'      => 'bool',
        'metadata'     => 'array',
    ];
}
