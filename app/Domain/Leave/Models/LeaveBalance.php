<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Employee\Models\Employee;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'employee_id', 'leave_type_id', 'fiscal_year',
        'opening', 'accrued', 'consumed', 'adjusted', 'closing',
    ];

    protected $casts = [
        'opening'  => 'decimal:2',
        'accrued'  => 'decimal:2',
        'consumed' => 'decimal:2',
        'adjusted' => 'decimal:2',
        'closing'  => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
