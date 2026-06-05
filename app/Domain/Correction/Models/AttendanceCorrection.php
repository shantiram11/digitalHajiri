<?php

declare(strict_types=1);

namespace App\Domain\Correction\Models;

use App\Domain\Employee\Models\Employee;
use App\Models\User;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCorrection extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'employee_id', 'requested_by_user_id',
        'work_date', 'requested_check_in', 'requested_check_out',
        'correction_type', 'reason', 'attachment_path',
        'status', 'decided_at', 'decided_by_user_id', 'decision_comment',
    ];

    protected $casts = [
        'work_date'           => 'date',
        'requested_check_in'  => 'datetime',
        'requested_check_out' => 'datetime',
        'decided_at'          => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionApproval::class, 'correction_id');
    }
}
