<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Employee\Models\Department;
use App\Domain\Employee\Models\Employee;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRule extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'department_id', 'employee_id', 'name',
        'office_start_time', 'office_end_time',
        'grace_minutes', 'early_grace_minutes',
        'overtime_threshold_minutes', 'overtime_requires_approval',
        'full_day_threshold_minutes', 'half_day_threshold_minutes',
        'absent_threshold_minutes', 'duplicate_window_seconds',
        'pairing_strategy', 'weekly_off_days',
        'effective_from', 'effective_to', 'is_active', 'extras',
    ];

    protected $casts = [
        'weekly_off_days'             => 'array',
        'extras'                      => 'array',
        'overtime_requires_approval'  => 'bool',
        'is_active'                   => 'bool',
        'effective_from'              => 'date',
        'effective_to'                => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
