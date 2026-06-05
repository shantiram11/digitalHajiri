<?php

declare(strict_types=1);

namespace App\Domain\Device\Jobs;

use App\Domain\Attendance\Jobs\GenerateAttendanceSummariesJob;
use App\Domain\Device\Models\Device;
use App\Domain\Device\Services\DeviceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled per-device sync. Unique per device so multiple ticks don't
 * stack on top of each other if a sync takes longer than the schedule.
 */
final class SyncDeviceLogsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 60;

    public function __construct(public readonly int $deviceId) {}

    public function uniqueId(): string
    {
        return "device-sync:{$this->deviceId}";
    }

    public function handle(DeviceSyncService $service): void
    {
        $device = Device::withoutGlobalScope(\App\Support\Eloquent\Scopes\OrganizationScope::class)
            ->find($this->deviceId);

        if ($device === null) {
            return;
        }

        $result = $service->sync($device);

        // Trigger summary regeneration for any (employee, work_date) the
        // ingest touched. The summary job is itself idempotent.
        if ($result->ingested > 0) {
            GenerateAttendanceSummariesJob::dispatch($device->id, $result->startedAt->format(DATE_ATOM));
        }
    }
}
