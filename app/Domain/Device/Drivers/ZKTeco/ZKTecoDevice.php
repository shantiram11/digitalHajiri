<?php

declare(strict_types=1);

namespace App\Domain\Device\Drivers\ZKTeco;

use App\Domain\Device\Contracts\AttendanceDeviceInterface;
use App\Domain\Device\DataTransferObjects\AttendanceLogDto;
use App\Domain\Device\DataTransferObjects\DeviceInfoDto;
use App\Domain\Device\DataTransferObjects\DeviceUserDto;
use App\Domain\Device\DataTransferObjects\SyncResultDto;
use App\Domain\Device\Exceptions\DeviceConnectionException;
use App\Domain\Device\Models\Device;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * ZKTeco TCP/UDP driver — skeleton.
 *
 * Phase 5 of the project plan will fill these methods using either the
 * `rats/zkteco` library or a low-level protocol implementation. Until then
 * the driver throws so consumers fail loudly during development if they
 * accidentally point at a real ZKTeco device.
 *
 * Time handling rule (must be followed when implemented):
 *   1. Read device-reported timezone from getDeviceInfo().deviceTimezone
 *      OR fall back to $this->device->timezone.
 *   2. Construct each AttendanceLogDto.timestamp as UTC by converting the
 *      device-local timestamp through that timezone.
 *   3. Record device_time + server_time on the Device row each connect() so
 *      drift can be observed externally.
 */
final class ZKTecoDevice implements AttendanceDeviceInterface
{
    private ?Device $device = null;
    private bool $connected = false;

    public function for(Device $device): self
    {
        $this->device = $device;
        return $this;
    }

    public function connect(): void
    {
        $this->assertDevice();
        // TODO(phase-5): Open TCP connection to $this->device->ip:$this->device->port
        //                using communication_key for auth.
        throw new DeviceConnectionException(
            'ZKTecoDevice::connect() is not yet implemented. Use FakeDevice for development.'
        );
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function ping(): bool
    {
        $this->assertDevice();
        // TODO(phase-5): low-cost reachability check (e.g., open socket + close).
        return false;
    }

    public function getDeviceInfo(): DeviceInfoDto
    {
        $this->notImplemented(__FUNCTION__);
    }

    public function getLogs(?DateTimeImmutable $since = null): iterable
    {
        $this->notImplemented(__FUNCTION__);
    }

    public function syncLogs(): SyncResultDto
    {
        $this->notImplemented(__FUNCTION__);
    }

    public function createUser(DeviceUserDto $user): void
    {
        $this->notImplemented(__FUNCTION__);
    }

    public function updateUser(DeviceUserDto $user): void
    {
        $this->notImplemented(__FUNCTION__);
    }

    public function deleteUser(string $deviceUserId): void
    {
        $this->notImplemented(__FUNCTION__);
    }

    public function syncEmployees(): SyncResultDto
    {
        $this->notImplemented(__FUNCTION__);
    }

    private function assertDevice(): void
    {
        if ($this->device === null) {
            throw new RuntimeException('ZKTecoDevice::for(Device) must be called first.');
        }
    }

    /** @return never */
    private function notImplemented(string $method): never
    {
        throw new RuntimeException(
            "ZKTecoDevice::{$method}() is scheduled for Phase 5 implementation."
        );
    }
}
