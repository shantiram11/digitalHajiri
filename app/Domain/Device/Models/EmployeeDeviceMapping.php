<?php

declare(strict_types=1);

namespace App\Domain\Device\Models;

use App\Domain\Employee\Models\Employee;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDeviceMapping extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'employee_id', 'device_id',
        'device_user_id', 'is_active', 'enrolled_at',
    ];

    protected $casts = [
        'is_active'   => 'bool',
        'enrolled_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
