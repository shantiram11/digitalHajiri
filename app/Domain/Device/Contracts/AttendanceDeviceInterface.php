<?php

declare(strict_types=1);

namespace App\Domain\Device\Contracts;

use App\Domain\Device\DataTransferObjects\AttendanceLogDto;
use App\Domain\Device\DataTransferObjects\DeviceInfoDto;
use App\Domain\Device\DataTransferObjects\DeviceUserDto;
use App\Domain\Device\DataTransferObjects\SyncResultDto;
use App\Domain\Device\Models\Device;
use DateTimeImmutable;

/**
 * Vendor-agnostic contract for any biometric attendance device.
 *
 * EVERY consumer (sync job, health monitor, provisioning UI, Artisan command)
 * depends on this interface and NEVER on a concrete vendor class. Adding a
 * new vendor is: implement this interface + register in DeviceConnectorFactory.
 *
 * @see docs/device-integration.md for the contract guarantees this interface
 *      makes about idempotency, time normalization, and failure modes.
 */
interface AttendanceDeviceInterface
{
    /**
     * Bind this driver instance to a device row before any operation.
     * Returns the same driver so calls can be chained.
     */
    public function for(Device $device): self;

    /**
     * Open the transport connection. Idempotent — calling twice is a no-op.
     */
    public function connect(): void;

    /**
     * Close the transport connection. Safe to call without connect().
     */
    public function disconnect(): void;

    /**
     * Cheap liveness probe. Returns true if the device is reachable.
     */
    public function ping(): bool;

    /**
     * Identity, firmware, capabilities, and the device's own clock.
     */
    public function getDeviceInfo(): DeviceInfoDto;

    /**
     * Fetch raw attendance logs as a generator/iterable so large fetches
     * don't blow memory.
     *
     * @param DateTimeImmutable|null $since  null = full sync; otherwise incremental
     * @return iterable<AttendanceLogDto>
     */
    public function getLogs(?DateTimeImmutable $since = null): iterable;

    /**
     * Pull logs, ingest them through the standard pipeline, and advance the
     * device cursor. Idempotent. Safe to retry on failure.
     */
    public function syncLogs(): SyncResultDto;

    /**
     * Push an employee record onto the device.
     * Fingerprint/face enrollment is performed on the device after this call.
     */
    public function createUser(DeviceUserDto $user): void;

    /**
     * Update an existing device user's metadata.
     */
    public function updateUser(DeviceUserDto $user): void;

    /**
     * Remove a device user. Their templates on the device are destroyed.
     */
    public function deleteUser(string $deviceUserId): void;

    /**
     * Reconcile employees: push missing, update changed, remove unmapped.
     * Source of truth is always the application database.
     */
    public function syncEmployees(): SyncResultDto;
}
