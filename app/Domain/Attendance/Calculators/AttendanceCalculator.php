<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Calculators;

use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\AttendanceRule;
use App\Domain\Attendance\Models\AttendanceSummary;
use App\Domain\Attendance\RuleEngine\RuleResolver;
use App\Domain\Attendance\Strategies\FirstInLastOutStrategy;
use App\Domain\Attendance\Strategies\PairingStrategy;
use App\Domain\Employee\Models\Employee;
use App\Domain\Holiday\Models\Holiday;
use App\Domain\Leave\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The heart of the attendance engine.
 *
 * Given (employee, work_date), it:
 *   1. Resolves the active rule (employee > department > org default).
 *   2. Detects exceptions in priority order: leave > holiday > weekly off > events.
 *   3. Pairs raw events using the configured strategy.
 *   4. Applies grace, late, early, OT thresholds from the rule.
 *   5. Upserts an AttendanceSummary row.
 *
 * This class is deliberately a thin orchestrator — every computation is
 * delegated to a strategy or helper so behavior can be replaced without
 * touching the calculator itself.
 *
 * The full implementation lands in Phase 6; this skeleton wires the inputs.
 */
final class AttendanceCalculator
{
    public function __construct(
        private readonly RuleResolver $ruleResolver,
    ) {}

    public function computeFor(Employee $employee, CarbonImmutable $workDate): AttendanceSummary
    {
        $rule = $this->ruleResolver->resolveFor($employee, $workDate);

        $leave   = $this->findApprovedLeave($employee, $workDate);
        $holiday = $this->findHoliday($employee, $workDate);
        $events  = $this->eventsForDay($employee, $workDate, $rule);

        $status = $this->determineStatus($events, $leave, $holiday, $rule, $workDate);

        $pairing = $this->strategyFor($rule)->pair($events);

        [$lateMinutes, $isLate] = $this->computeLateness($pairing->checkInAt, $rule);
        [$earlyMinutes, $isEarly] = $this->computeEarliness($pairing->checkOutAt, $rule);
        $overtimeMinutes = $this->computeOvertime($pairing->checkOutAt, $rule);

        return DB::transaction(function () use (
            $employee, $workDate, $rule, $status, $leave,
            $pairing, $lateMinutes, $earlyMinutes, $overtimeMinutes, $isLate, $isEarly
        ): AttendanceSummary {
            return AttendanceSummary::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'work_date' => $workDate->toDateString()],
                [
                    'organization_id'   => $employee->organization_id,
                    'rule_id'           => $rule?->id,
                    'status'            => $status,
                    'leave_type_slug'   => $leave?->leaveType?->slug,
                    'check_in_at'       => $pairing->checkInAt,
                    'check_out_at'      => $pairing->checkOutAt,
                    'worked_minutes'    => $pairing->workedMinutes,
                    'late_minutes'      => $lateMinutes,
                    'early_minutes'     => $earlyMinutes,
                    'overtime_minutes'  => $overtimeMinutes,
                    'is_late'           => $isLate,
                    'is_early'          => $isEarly,
                    'overtime_approved' => false,
                    'event_ids'         => $pairing->eventIds,
                    'sources'           => $this->sourcesSnapshot($events, $leave, $rule),
                    'generated_at'      => now(),
                ],
            );
        });
    }

    private function strategyFor(?AttendanceRule $rule): PairingStrategy
    {
        // Only FirstInLastOut is implemented at MVP; PairInOut lands in Phase 6.
        return new FirstInLastOutStrategy;
    }

    private function eventsForDay(Employee $employee, CarbonImmutable $workDate, ?AttendanceRule $rule)
    {
        $timezone = $employee->organization?->timezone ?? config('app.timezone');
        $start = $workDate->copy()->setTimezone($timezone)->startOfDay()->utc();
        $end   = $workDate->copy()->setTimezone($timezone)->endOfDay()->utc();

        return AttendanceEvent::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('event_timestamp', [$start, $end])
            ->orderBy('event_timestamp')
            ->get();
    }

    private function findApprovedLeave(Employee $employee, CarbonImmutable $workDate): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $workDate)
            ->whereDate('to_date', '>=', $workDate)
            ->first();
    }

    private function findHoliday(Employee $employee, CarbonImmutable $workDate): ?Holiday
    {
        return Holiday::query()
            ->where(function ($q) use ($employee) {
                $q->where('organization_id', $employee->organization_id)
                  ->orWhereNull('organization_id');
            })
            ->whereDate('date', $workDate)
            ->first();
    }

    private function determineStatus(
        $events,
        ?LeaveRequest $leave,
        ?Holiday $holiday,
        ?AttendanceRule $rule,
        CarbonImmutable $workDate,
    ): string {
        if ($leave) {
            return 'on_leave';
        }
        if ($holiday) {
            return 'holiday';
        }
        if ($rule && in_array($workDate->englishDayOfWeek, (array) $rule->weekly_off_days, true)) {
            return 'weekly_off';
        }
        if ($events->isEmpty()) {
            return 'absent';
        }

        $worked = $rule?->half_day_threshold_minutes ?? 240;
        $hasCheckOut = $events->count() >= 2;
        $minutes = $hasCheckOut
            ? (int) CarbonImmutable::instance($events->first()->event_timestamp)
                ->diffInMinutes(CarbonImmutable::instance($events->last()->event_timestamp))
            : 0;

        if ($minutes >= ($rule?->full_day_threshold_minutes ?? 480)) {
            return 'present';
        }
        if ($minutes >= $worked) {
            return 'half_day';
        }
        return 'absent';
    }

    /** @return array{int,bool} */
    private function computeLateness(?CarbonImmutable $checkIn, ?AttendanceRule $rule): array
    {
        if ($checkIn === null || $rule === null) {
            return [0, false];
        }

        $expected = $checkIn->copy()
            ->setTimeFromTimeString($rule->office_start_time)
            ->addMinutes($rule->grace_minutes);

        if ($checkIn->lessThanOrEqualTo($expected)) {
            return [0, false];
        }

        $startNoGrace = $checkIn->copy()->setTimeFromTimeString($rule->office_start_time);
        $late = (int) $startNoGrace->diffInMinutes($checkIn);

        return [$late, true];
    }

    /** @return array{int,bool} */
    private function computeEarliness(?CarbonImmutable $checkOut, ?AttendanceRule $rule): array
    {
        if ($checkOut === null || $rule === null) {
            return [0, false];
        }

        $expectedEnd = $checkOut->copy()
            ->setTimeFromTimeString($rule->office_end_time)
            ->subMinutes($rule->early_grace_minutes);

        if ($checkOut->greaterThanOrEqualTo($expectedEnd)) {
            return [0, false];
        }

        $endNoGrace = $checkOut->copy()->setTimeFromTimeString($rule->office_end_time);
        $early = (int) $checkOut->diffInMinutes($endNoGrace);

        return [$early, true];
    }

    private function computeOvertime(?CarbonImmutable $checkOut, ?AttendanceRule $rule): int
    {
        if ($checkOut === null || $rule === null) {
            return 0;
        }

        $threshold = $checkOut->copy()
            ->setTimeFromTimeString($rule->office_end_time)
            ->addMinutes($rule->overtime_threshold_minutes);

        return $checkOut->greaterThan($threshold)
            ? (int) $threshold->diffInMinutes($checkOut)
            : 0;
    }

    private function sourcesSnapshot($events, ?LeaveRequest $leave, ?AttendanceRule $rule): array
    {
        return [
            'event_count' => $events->count(),
            'devices'     => $events->pluck('device_id')->filter()->unique()->values()->all(),
            'leave_id'    => $leave?->id,
            'rule_id'     => $rule?->id,
        ];
    }
}
