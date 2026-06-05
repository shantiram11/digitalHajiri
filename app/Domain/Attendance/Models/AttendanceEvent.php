<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Device\Models\Device;
use App\Domain\Employee\Models\Employee;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raw biometric punch. Append-only.
 *
 * Do NOT use ::update() or ::delete() on this model. Corrections create
 * *new* events with source='correction'; they never mutate prior rows.
 */
class AttendanceEvent extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'employee_id', 'device_id', 'device_user_id',
        'event_timestamp', 'verification_method', 'source',
        'device_log_id', 'raw_payload', 'ingested_at',
    ];

    protected $casts = [
        'event_timestamp' => 'datetime',
        'ingested_at'     => 'datetime',
        'raw_payload'     => 'array',
        'device_log_id'   => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isOrphan(): bool
    {
        return $this->employee_id === null;
    }
}
