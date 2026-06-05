<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Employee\Models\Employee;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSummary extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'employee_id', 'rule_id',
        'work_date', 'status', 'leave_type_slug',
        'check_in_at', 'check_out_at',
        'worked_minutes', 'late_minutes', 'early_minutes', 'overtime_minutes',
        'is_late', 'is_early', 'overtime_approved',
        'event_ids', 'sources', 'generated_at',
    ];

    protected $casts = [
        'work_date'         => 'date',
        'check_in_at'       => 'datetime',
        'check_out_at'      => 'datetime',
        'event_ids'         => 'array',
        'sources'           => 'array',
        'is_late'           => 'bool',
        'is_early'          => 'bool',
        'overtime_approved' => 'bool',
        'generated_at'      => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AttendanceRule::class, 'rule_id');
    }

    public function auditExcludedAttributes(): array
    {
        return ['updated_at', 'generated_at'];
    }
}
