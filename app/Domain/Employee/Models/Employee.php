<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\AttendanceSummary;
use App\Domain\Device\Models\Device;
use App\Domain\Device\Models\EmployeeDeviceMapping;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveRequest;
use App\Models\User;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes, BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'department_id', 'designation_id', 'user_id',
        'employee_code', 'first_name', 'middle_name', 'last_name',
        'email', 'phone', 'date_of_birth', 'gender', 'citizenship_no',
        'employment_type', 'joined_at', 'resigned_at', 'status', 'metadata',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joined_at'     => 'date',
        'resigned_at'   => 'date',
        'metadata'      => 'array',
    ];

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceMappings(): HasMany
    {
        return $this->hasMany(EmployeeDeviceMapping::class);
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'employee_device_mappings')
            ->withPivot(['device_user_id', 'is_active', 'enrolled_at'])
            ->withTimestamps();
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
