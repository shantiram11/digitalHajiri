<?php

declare(strict_types=1);

namespace App\Domain\Device\DataTransferObjects;

use DateTimeImmutable;

/**
 * Snapshot of a device's identity, time, and capabilities.
 *
 * Returned by AttendanceDeviceInterface::getDeviceInfo(). Used by health
 * monitoring to detect clock drift and by provisioning code to verify the
 * device supports the features the operator is trying to use.
 */
final readonly class DeviceInfoDto
{
    /**
     * @param array<int, string> $capabilities  e.g. ['fingerprint','face','card','push']
     */
    public function __construct(
        public string $vendor,
        public string $model,
        public string $serial,
        public string $firmware,
        public string $deviceTimezone,
        public DateTimeImmutable $deviceTime,
        public int $userCount,
        public int $logCount,
        public array $capabilities,
    ) {}
}
