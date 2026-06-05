<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Employee\Models\Employee;
use App\Models\User;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes, BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'employee_id', 'leave_type_id', 'applied_by_user_id',
        'from_date', 'to_date', 'days', 'session', 'reason', 'attachment_path',
        'status', 'applied_at', 'decided_at', 'decided_by_user_id', 'decision_comment',
    ];

    protected $casts = [
        'from_date'   => 'date',
        'to_date'     => 'date',
        'days'        => 'decimal:2',
        'applied_at'  => 'datetime',
        'decided_at'  => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(\App\Domain\Identity\Models\Approval::class, 'approvable');
    }
}
