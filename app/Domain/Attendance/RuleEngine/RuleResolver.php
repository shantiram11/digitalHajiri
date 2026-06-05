<?php

declare(strict_types=1);

namespace App\Domain\Attendance\RuleEngine;

use App\Domain\Attendance\Models\AttendanceRule;
use App\Domain\Employee\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Resolves the active AttendanceRule for an employee on a given date.
 *
 * Precedence (most specific wins):
 *   1. employee-level rule active on the date
 *   2. department-level rule active on the date
 *   3. organization-level default rule active on the date
 *
 * "Active" means is_active=true AND effective_from <= date <= effective_to (nulls open).
 */
final class RuleResolver
{
    public function resolveFor(Employee $employee, CarbonImmutable $date): ?AttendanceRule
    {
        $query = AttendanceRule::query()
            ->where('organization_id', $employee->organization_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));

        $employeeRule = (clone $query)
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->first();
        if ($employeeRule) {
            return $employeeRule;
        }

        if ($employee->department_id) {
            $deptRule = (clone $query)
                ->whereNull('employee_id')
                ->where('department_id', $employee->department_id)
                ->latest('id')
                ->first();
            if ($deptRule) {
                return $deptRule;
            }
        }

        return (clone $query)
            ->whereNull('employee_id')
            ->whereNull('department_id')
            ->latest('id')
            ->first();
    }
}
