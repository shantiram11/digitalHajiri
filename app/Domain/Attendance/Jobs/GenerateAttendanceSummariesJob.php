<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Jobs;

use App\Domain\Attendance\Calculators\AttendanceCalculator;
use App\Domain\Attendance\Models\AttendanceEvent;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recomputes summaries for every (employee, work_date) pair affected by
 * events on the given device since `sinceIso`. Idempotent — running twice
 * just regenerates the same rows.
 */
final class GenerateAttendanceSummariesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $deviceId,
        public readonly string $sinceIso,
    ) {}

    public function handle(AttendanceCalculator $calculator): void
    {
        $since = CarbonImmutable::parse($this->sinceIso);

        $pairs = AttendanceEvent::query()
            ->withoutGlobalScope(\App\Support\Eloquent\Scopes\OrganizationScope::class)
            ->where('device_id', $this->deviceId)
            ->where('ingested_at', '>=', $since)
            ->whereNotNull('employee_id')
            ->selectRaw('employee_id, DATE(event_timestamp) as work_date')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $employee = \App\Domain\Employee\Models\Employee::query()
                ->withoutGlobalScope(\App\Support\Eloquent\Scopes\OrganizationScope::class)
                ->find($pair->employee_id);
            if ($employee === null) {
                continue;
            }
            $calculator->computeFor($employee, CarbonImmutable::parse($pair->work_date));
        }
    }
}
