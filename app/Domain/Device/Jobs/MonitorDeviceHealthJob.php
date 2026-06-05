<?php

declare(strict_types=1);

namespace App\Domain\Device\Jobs;

use App\Domain\Device\Contracts\DeviceConnectorFactoryInterface;
use App\Domain\Device\Models\Device;
use App\Support\Eloquent\Scopes\OrganizationScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scheduled every minute. For every active device:
 *   - ping via the driver
 *   - update last_online_at on success
 *   - mark status = offline if last_online_at exceeds the threshold
 *   - log a warning when clock drift exceeds the threshold
 *
 * Push-based vendor alerts can call this directly for one device.
 */
final class MonitorDeviceHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $offlineThresholdSeconds = 900;       // 15 minutes
    public int $clockDriftWarnSeconds   = 60;
    public int $clockDriftCriticalSeconds = 300;

    public function __construct(public readonly ?int $deviceId = null) {}

    public function handle(DeviceConnectorFactoryInterface $factory): void
    {
        $query = Device::withoutGlobalScope(OrganizationScope::class)
            ->where('status', '!=', 'disabled');

        if ($this->deviceId !== null) {
            $query->where('id', $this->deviceId);
        }

        foreach ($query->cursor() as $device) {
            try {
                $driver = $factory->for($device);
                $driver->connect();
                $alive = $driver->ping();
                $driver->disconnect();

                if ($alive) {
                    $device->forceFill(['last_online_at' => now(), 'status' => 'active'])->save();
                } else {
                    $this->markOfflineIfStale($device);
                }
            } catch (Throwable $e) {
                Log::info('MonitorDeviceHealthJob: device unreachable', [
                    'device_id' => $device->id,
                    'error'     => $e->getMessage(),
                ]);
                $this->markOfflineIfStale($device);
            }

            if ($device->clock_drift_seconds !== null
                && abs((int) $device->clock_drift_seconds) >= $this->clockDriftWarnSeconds
            ) {
                Log::warning('Device clock drift detected', [
                    'device_id' => $device->id,
                    'drift'     => $device->clock_drift_seconds,
                    'critical'  => abs((int) $device->clock_drift_seconds) >= $this->clockDriftCriticalSeconds,
                ]);
            }
        }
    }

    private function markOfflineIfStale(Device $device): void
    {
        if ($device->last_online_at === null
            || $device->last_online_at->diffInSeconds(now()) > $this->offlineThresholdSeconds
        ) {
            $device->forceFill(['status' => 'offline'])->save();
        }
    }
}
