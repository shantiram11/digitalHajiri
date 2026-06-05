<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'name', 'slug', 'description',
        'default_quota_days', 'is_paid', 'counts_weekly_off',
        'counts_holidays', 'requires_attachment_after_days',
        'allow_half_day', 'is_active', 'settings',
    ];

    protected $casts = [
        'default_quota_days' => 'decimal:2',
        'is_paid'            => 'bool',
        'counts_weekly_off'  => 'bool',
        'counts_holidays'    => 'bool',
        'allow_half_day'     => 'bool',
        'is_active'          => 'bool',
        'settings'           => 'array',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
