<?php

declare(strict_types=1);

namespace App\Domain\Device\Services;

use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Device\Contracts\DeviceConnectorFactoryInterface;
use App\Domain\Device\DataTransferObjects\AttendanceLogDto;
use App\Domain\Device\DataTransferObjects\SyncResultDto;
use App\Domain\Device\Models\Device;
use App\Domain\Device\Models\EmployeeDeviceMapping;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drives the full sync cycle for a single device:
 *
 *   factory → driver → connect → pull logs → ingest (idempotent) →
 *   advance cursor → recompute affected summaries.
 *
 * Ingest is idempotent thanks to the natural-key unique index on
 * attendance_events. Re-running this method after a partial failure is
 * always safe.
 */
final class DeviceSyncService
{
    public function __construct(
        private readonly DeviceConnectorFactoryInterface $factory,
    ) {}

    public function sync(Device $device): SyncResultDto
    {
        $startedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $driver    = $this->factory->for($device);
        $errors    = [];

        $fetched = $ingested = $duplicates = $orphans = 0;
        $maxCursor = null;

        try {
            $driver->connect();

            $info = $driver->getDeviceInfo();
            $device->forceFill([
                'firmware'           => $info->firmware,
                'device_time'        => $info->deviceTime,
                'server_time'        => now(),
                'clock_drift_seconds'=> (int) ($info->deviceTime->getTimestamp() - time()),
                'last_online_at'     => now(),
            ])->save();

            $since = $device->last_log_id === null ? null : null; // last_log_id is a cursor, not a date; use ::null for now.
            // The cursor-driven path: drivers that emit deviceLogId let us skip
            // records we have already seen via the unique index. Drivers that
            // emit no cursor (push, software events) fall back to "since now".

            foreach ($driver->getLogs($since) as $log) {
                $fetched++;

                $result = $this->ingestSingle($device, $log);
                if ($result === 'inserted')  $ingested++;
                if ($result === 'duplicate') $duplicates++;
                if ($result === 'orphan')    $orphans++;

                if ($log->deviceLogId !== null && ($maxCursor === null || $log->deviceLogId > $maxCursor)) {
                    $maxCursor = $log->deviceLogId;
                }
            }

            if ($maxCursor !== null) {
                $device->forceFill([
                    'last_log_id'  => $maxCursor,
                    'last_sync_at' => now(),
                ])->save();
            }
        } catch (Throwable $e) {
            $errors['exception'] = $e->getMessage();
            Log::warning('DeviceSyncService::sync failed', [
                'device_id' => $device->id,
                'error'     => $e->getMessage(),
            ]);
        } finally {
            try {
                $driver->disconnect();
            } catch (Throwable $e) {
                // disconnect errors are non-fatal — log and move on.
                Log::info('DeviceSyncService disconnect non-fatal', ['error' => $e->getMessage()]);
            }
        }

        return new SyncResultDto(
            fetched: $fetched,
            ingested: $ingested,
            duplicates: $duplicates,
            orphans: $orphans,
            newCursor: $maxCursor,
            errors: $errors,
            startedAt: $startedAt,
            finishedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    private function ingestSingle(Device $device, AttendanceLogDto $log): string
    {
        $mapping = EmployeeDeviceMapping::query()
            ->where('device_id', $device->id)
            ->where('device_user_id', $log->deviceUserId)
            ->where('is_active', true)
            ->first();

        try {
            DB::transaction(function () use ($device, $log, $mapping) {
                AttendanceEvent::query()->create([
                    'organization_id'      => $device->organization_id,
                    'employee_id'          => $mapping?->employee_id,
                    'device_id'            => $device->id,
                    'device_user_id'       => $log->deviceUserId,
                    'event_timestamp'      => $log->timestamp,
                    'verification_method'  => $log->verificationMethod,
                    'source'               => 'device',
                    'device_log_id'        => $log->deviceLogId,
                    'raw_payload'          => $log->raw,
                    'ingested_at'          => now(),
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return 'duplicate';
        } catch (\Illuminate\Database\QueryException $e) {
            // Some DB drivers don't surface UniqueConstraintViolationException
            // — sniff for "Integrity constraint" / "UNIQUE" in the SQLSTATE.
            if (str_contains((string) $e->getCode(), '23')) {
                return 'duplicate';
            }
            throw $e;
        }

        return $mapping === null ? 'orphan' : 'inserted';
    }
}
