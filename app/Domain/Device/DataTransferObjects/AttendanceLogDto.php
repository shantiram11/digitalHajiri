<?php

declare(strict_types=1);

namespace App\Domain\Device\DataTransferObjects;

use DateTimeImmutable;

/**
 * A single biometric punch as received from a device.
 *
 * `timestamp` is ALWAYS UTC by the time it leaves the driver — the driver
 * is responsible for converting from the device-reported timezone using
 * the device's `timezone` column or the `DeviceInfoDto.deviceTimezone`.
 *
 * `deviceLogId` is the vendor's cursor for that log. Used to advance
 * `devices.last_log_id` after ingest. Nullable for vendors that don't
 * provide one (push events, software punches).
 *
 * `raw` is the verbatim vendor payload — kept so we can re-process events
 * if a future bug in the mapper is fixed and old events need replaying.
 */
final readonly class AttendanceLogDto
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $deviceUserId,
        public DateTimeImmutable $timestamp,
        public string $verificationMethod,
        public ?int $deviceLogId,
        public array $raw,
    ) {}
}
