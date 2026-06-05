<?php

declare(strict_types=1);

namespace App\Domain\Device\Models;

use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Employee\Models\Department;
use App\Domain\Employee\Models\Employee;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory, SoftDeletes, BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'department_id', 'name', 'vendor', 'model', 'serial',
        'firmware', 'location', 'ip', 'port', 'communication_key', 'settings',
        'timezone', 'device_time', 'server_time', 'clock_drift_seconds',
        'last_online_at', 'last_sync_at', 'last_log_id', 'status',
    ];

    protected $hidden = ['communication_key'];

    protected $casts = [
        'settings'            => 'array',
        'device_time'         => 'datetime',
        'server_time'         => 'datetime',
        'last_online_at'      => 'datetime',
        'last_sync_at'        => 'datetime',
        'last_log_id'         => 'integer',
        'clock_drift_seconds' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(EmployeeDeviceMapping::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_device_mappings')
            ->withPivot(['device_user_id', 'is_active', 'enrolled_at'])
            ->withTimestamps();
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function isOnline(int $offlineThresholdSeconds = 900): bool
    {
        return $this->last_online_at !== null
            && $this->last_online_at->diffInSeconds(now()) < $offlineThresholdSeconds;
    }

    public function auditExcludedAttributes(): array
    {
        return ['updated_at', 'last_online_at', 'last_sync_at', 'device_time', 'server_time'];
    }
}
