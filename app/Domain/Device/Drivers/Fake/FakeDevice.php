<?php

declare(strict_types=1);

namespace App\Domain\Device\Drivers\Fake;

use App\Domain\Device\Contracts\AttendanceDeviceInterface;
use App\Domain\Device\DataTransferObjects\AttendanceLogDto;
use App\Domain\Device\DataTransferObjects\DeviceInfoDto;
use App\Domain\Device\DataTransferObjects\DeviceUserDto;
use App\Domain\Device\DataTransferObjects\SyncResultDto;
use App\Domain\Device\Models\Device;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * In-memory driver used for local development, automated tests, and the
 * contract test suite. Implements the full interface without touching any
 * vendor SDK or physical hardware.
 *
 * It is also the canonical reference implementation: any new vendor driver
 * must pass the same contract tests that FakeDevice does.
 */
final class FakeDevice implements AttendanceDeviceInterface
{
    private ?Device $device = null;
    private bool $connected = false;

    /** @var array<int, AttendanceLogDto> */
    private array $logs = [];

    /** @var array<string, DeviceUserDto> */
    private array $users = [];

    private int $nextLogId = 1;

    public function for(Device $device): self
    {
        $this->device = $device;
        return $this;
    }

    public function connect(): void
    {
        $this->assertDevice();
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function ping(): bool
    {
        return $this->connected || true; // Fake device is always reachable.
    }

    public function getDeviceInfo(): DeviceInfoDto
    {
        $this->assertDevice();
        $this->assertConnected();

        return new DeviceInfoDto(
            vendor: 'fake',
            model: 'Fake-1000',
            serial: $this->device->serial ?? 'FAKE-SERIAL',
            firmware: '1.0.0',
            deviceTimezone: $this->device->timezone ?? 'Asia/Kathmandu',
            deviceTime: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            userCount: count($this->users),
            logCount: count($this->logs),
            capabilities: ['fingerprint', 'card', 'manual'],
        );
    }

    public function getLogs(?DateTimeImmutable $since = null): iterable
    {
        $this->assertConnected();

        foreach ($this->logs as $log) {
            if ($since !== null && $log->timestamp < $since) {
                continue;
            }
            yield $log;
        }
    }

    public function syncLogs(): SyncResultDto
    {
        $this->assertConnected();
        $start = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $fetched = count($this->logs);

        // The real ingest pipeline is invoked by DeviceSyncService; FakeDevice
        // does not write to the DB itself. This is a stub for tests that only
        // care about the wire shape.
        return new SyncResultDto(
            fetched: $fetched,
            ingested: 0,
            duplicates: 0,
            orphans: 0,
            newCursor: $this->nextLogId - 1 > 0 ? $this->nextLogId - 1 : null,
            errors: [],
            startedAt: $start,
            finishedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    public function createUser(DeviceUserDto $user): void
    {
        $this->assertConnected();
        $this->users[$user->deviceUserId] = $user;
    }

    public function updateUser(DeviceUserDto $user): void
    {
        $this->createUser($user);
    }

    public function deleteUser(string $deviceUserId): void
    {
        $this->assertConnected();
        unset($this->users[$deviceUserId]);
    }

    public function syncEmployees(): SyncResultDto
    {
        $this->assertConnected();
        $start = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new SyncResultDto(
            fetched: count($this->users),
            ingested: 0,
            duplicates: 0,
            orphans: 0,
            newCursor: null,
            errors: [],
            startedAt: $start,
            finishedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    // ---- Test helpers (not part of the interface) ----

    /**
     * Inject a punch into the fake device's internal buffer.
     * Tests use this to simulate a device having unread events.
     */
    public function pushLog(string $deviceUserId, DateTimeImmutable $at, string $method = 'fingerprint'): AttendanceLogDto
    {
        $log = new AttendanceLogDto(
            deviceUserId: $deviceUserId,
            timestamp: $at,
            verificationMethod: $method,
            deviceLogId: $this->nextLogId++,
            raw: ['injected' => true],
        );
        $this->logs[] = $log;
        return $log;
    }

    private function assertDevice(): void
    {
        if ($this->device === null) {
            throw new RuntimeException('FakeDevice::for(Device) must be called first.');
        }
    }

    private function assertConnected(): void
    {
        if (! $this->connected) {
            throw new RuntimeException('FakeDevice is not connected. Call connect() first.');
        }
    }
}
